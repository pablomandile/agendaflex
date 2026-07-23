<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WidgetApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'timezone' => 'America/Argentina/Buenos_Aires',
        ]);

        app(CurrentCompany::class)->set($this->company);

        $this->branch = Branch::factory()->for($this->company)->create();
        $this->employee = Employee::factory()->for($this->company)->create(['branch_id' => $this->branch->id]);
        $this->service = Service::factory()->for($this->company)->create(['duration_minutes' => 60]);
        $this->employee->services()->attach($this->service->id);

        foreach (range(1, 5) as $day) {
            $this->employee->workingHours()->create([
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '18:00',
            ]);
        }

        // Los requests del widget no traen sesión: limpiar el tenant
        // seteado para el setUp y dejar que lo resuelva el middleware
        app(CurrentCompany::class)->set(null);
    }

    private function headers(?string $key = null): array
    {
        return ['X-Public-Key' => $key ?? $this->company->public_key];
    }

    private function url(string $path): string
    {
        return "/api/v1/{$this->company->slug}/{$path}";
    }

    public function test_requires_public_key()
    {
        $this->getJson($this->url('catalog'))->assertUnauthorized();
    }

    public function test_rejects_wrong_public_key_with_404()
    {
        $this->getJson($this->url('catalog'), $this->headers('pk_invalida'))
            ->assertNotFound();
    }

    public function test_rejects_suspended_company()
    {
        $this->company->update(['status' => 'suspended']);

        $this->getJson($this->url('catalog'), $this->headers())
            ->assertNotFound();
    }

    public function test_rejects_disallowed_origin()
    {
        $this->company->update(['allowed_origins' => ['https://permitido.com']]);

        $this->getJson($this->url('catalog'), [
            ...$this->headers(),
            'Origin' => 'https://intruso.com',
        ])->assertForbidden();

        $this->getJson($this->url('catalog'), [
            ...$this->headers(),
            'Origin' => 'https://permitido.com',
        ])->assertOk();
    }

    public function test_catalog_returns_public_identifiers_only()
    {
        $response = $this->getJson($this->url('catalog'), $this->headers());

        $response->assertOk()
            ->assertJsonPath('company.name', $this->company->name)
            ->assertJsonPath('services.0.uuid', $this->service->uuid)
            ->assertJsonPath('branches.0.slug', $this->branch->slug)
            ->assertJsonPath('employees.0.uuid', $this->employee->uuid);

        // Nunca exponer IDs internos
        $this->assertArrayNotHasKey('id', $response->json('services.0'));
        $this->assertArrayNotHasKey('id', $response->json('employees.0'));
        $this->assertArrayNotHasKey('id', $response->json('branches.0'));
    }

    public function test_catalog_is_scoped_to_the_tenant()
    {
        $foreign = Company::factory()->create();
        Service::factory()->for($foreign)->count(4)->create();

        $response = $this->getJson($this->url('catalog'), $this->headers());

        $this->assertCount(1, $response->json('services'));
    }

    public function test_availability_returns_slots_with_employee_uuid()
    {
        $monday = CarbonImmutable::now($this->company->timezone)->next(CarbonImmutable::MONDAY);

        $response = $this->getJson($this->url(
            "availability?branch={$this->branch->slug}&service={$this->service->uuid}&date={$monday->toDateString()}",
        ), $this->headers());

        $response->assertOk();
        $this->assertNotEmpty($response->json());
        $this->assertSame($this->employee->uuid, $response->json('0.employee_uuid'));
    }

    public function test_booking_creates_appointment_and_returns_manage_url()
    {
        $monday = CarbonImmutable::now($this->company->timezone)
            ->next(CarbonImmutable::MONDAY)
            ->setTime(10, 0)
            ->utc();

        $response = $this->postJson($this->url('bookings'), [
            'branch' => $this->branch->slug,
            'service' => $this->service->uuid,
            'employee' => $this->employee->uuid,
            'starts_at' => $monday->toIso8601ZuluString(),
            'customer' => [
                'name' => 'Cliente Widget',
                'email' => 'widget@example.com',
                'phone' => '11-4444-0000',
            ],
        ], $this->headers());

        $response->assertCreated()
            ->assertJsonPath('booking.status', 'confirmed')
            ->assertJsonPath('booking.service', $this->service->name);

        $this->assertStringContainsString('/booking/', $response->json('manage_url'));
        $this->assertStringContainsString('signature=', $response->json('manage_url'));

        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'source' => 'widget',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('customers', ['email' => 'widget@example.com']);
    }

    public function test_booking_conflict_returns_422()
    {
        $monday = CarbonImmutable::now($this->company->timezone)
            ->next(CarbonImmutable::MONDAY)
            ->setTime(10, 0)
            ->utc();

        $payload = fn (string $email) => [
            'branch' => $this->branch->slug,
            'service' => $this->service->uuid,
            'employee' => $this->employee->uuid,
            'starts_at' => $monday->toIso8601ZuluString(),
            'customer' => ['name' => 'X', 'email' => $email],
        ];

        $this->postJson($this->url('bookings'), $payload('a@example.com'), $this->headers())
            ->assertCreated();

        $this->postJson($this->url('bookings'), $payload('b@example.com'), $this->headers())
            ->assertStatus(422);
    }

    public function test_manage_page_requires_valid_signature()
    {
        $appointment = $this->makeAppointment();

        // Sin firma => 403
        $this->get("/booking/{$appointment->uuid}")->assertForbidden();

        // Con firma => 200 con el detalle
        $this->get(URL::signedRoute('booking.manage', ['appointment' => $appointment->uuid]))
            ->assertOk()
            ->assertSee($appointment->service->name);
    }

    public function test_signed_cancel_cancels_the_appointment()
    {
        $appointment = $this->makeAppointment();

        $this->post(URL::signedRoute('booking.cancel', ['appointment' => $appointment->uuid]))
            ->assertRedirect();

        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    private function makeAppointment(): Appointment
    {
        app(CurrentCompany::class)->set($this->company);

        $start = CarbonImmutable::now($this->company->timezone)
            ->next(CarbonImmutable::MONDAY)
            ->setTime(11, 0)
            ->utc();

        $appointment = Appointment::factory()->for($this->company)->recycle($this->company)->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);

        app(CurrentCompany::class)->set(null);

        return $appointment;
    }
}
