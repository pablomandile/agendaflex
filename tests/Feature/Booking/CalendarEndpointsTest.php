<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private Employee $employee;

    private Service $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->company = Company::factory()->create();

        // Las creaciones del setUp corren fuera de un request: el trait
        // BelongsToCompany necesita el tenant seteado para company_id
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

        $this->owner = User::factory()->create();
        $this->owner->companies()->attach($this->company);
        setPermissionsTeamId($this->company->id);
        $this->owner->assignRole('owner');
    }

    private function nextMondayAt(int $hour): CarbonImmutable
    {
        return CarbonImmutable::now($this->company->timezone)
            ->next(CarbonImmutable::MONDAY)
            ->setTime($hour, 0)
            ->utc();
    }

    public function test_calendar_page_renders_with_scoped_props()
    {
        $foreign = Company::factory()->create();
        Service::factory()->for($foreign)->count(3)->create();

        $this->actingAs($this->owner)
            ->get(route('calendar'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('calendar/Index')
                ->has('services', 1)
                ->has('employees', 1)
                ->has('branches', 1));
    }

    public function test_events_endpoint_returns_only_own_company_appointments()
    {
        $start = $this->nextMondayAt(10);

        Appointment::factory()->for($this->company)->recycle($this->company)->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);

        // Turno de otra empresa en el mismo rango
        $foreign = Company::factory()->create();
        Appointment::factory()->for($foreign)->recycle($foreign)->create([
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);

        $response = $this->actingAs($this->owner)->getJson(
            route('calendar.events', [
                'start' => $start->subDay()->toDateString(),
                'end' => $start->addDay()->toDateString(),
            ]),
        );

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_availability_endpoint_returns_slots()
    {
        $monday = CarbonImmutable::now($this->company->timezone)->next(CarbonImmutable::MONDAY);

        $response = $this->actingAs($this->owner)->getJson(route('availability', [
            'branch_id' => $this->branch->id,
            'service_id' => $this->service->id,
            'date' => $monday->toDateString(),
        ]));

        $response->assertOk();
        $this->assertNotEmpty($response->json());
        $this->assertArrayHasKey('starts_at', $response->json()[0]);
        $this->assertArrayHasKey('label', $response->json()[0]);
    }

    public function test_store_creates_appointment_with_new_customer()
    {
        $start = $this->nextMondayAt(10);

        $this->actingAs($this->owner)
            ->post(route('appointments.store'), [
                'branch_id' => $this->branch->id,
                'service_id' => $this->service->id,
                'employee_id' => $this->employee->id,
                'starts_at' => $start->toIso8601ZuluString(),
                'customer' => ['name' => 'Cliente Nuevo', 'email' => 'cliente@example.com'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('customers', ['email' => 'cliente@example.com']);
    }

    public function test_store_rejects_conflicting_slot_with_validation_error()
    {
        $start = $this->nextMondayAt(10);

        Appointment::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);

        $this->actingAs($this->owner)
            ->from(route('calendar'))
            ->post(route('appointments.store'), [
                'branch_id' => $this->branch->id,
                'service_id' => $this->service->id,
                'employee_id' => $this->employee->id,
                'starts_at' => $start->toIso8601ZuluString(),
                'customer' => ['name' => 'Otro', 'email' => 'otro@example.com'],
            ])
            ->assertRedirect(route('calendar'))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_cancel_endpoint_cancels_appointment()
    {
        $start = $this->nextMondayAt(10);

        $appointment = Appointment::factory()->for($this->company)->recycle($this->company)->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);

        $this->actingAs($this->owner)
            ->post(route('appointments.cancel', $appointment))
            ->assertRedirect();

        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_cross_tenant_appointment_returns_404()
    {
        $foreign = Company::factory()->create();
        $foreignAppointment = Appointment::factory()->for($foreign)->recycle($foreign)->create();

        $this->actingAs($this->owner)
            ->post(route('appointments.cancel', $foreignAppointment))
            ->assertNotFound();
    }

    public function test_user_without_role_cannot_access_calendar()
    {
        $intruder = User::factory()->create();
        $intruder->companies()->attach($this->company);

        $this->actingAs($intruder)
            ->get(route('calendar'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login()
    {
        $this->get(route('calendar'))->assertRedirect(route('login'));
    }
}
