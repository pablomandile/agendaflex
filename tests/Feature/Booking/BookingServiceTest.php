<?php

namespace Tests\Feature\Booking;

use App\Exceptions\SlotUnavailableException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private Employee $employee;

    private Service $service;

    private CarbonImmutable $slotStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency' => 'ARS',
        ]);

        app(CurrentCompany::class)->set($this->company);

        $this->branch = Branch::factory()->for($this->company)->create();
        $this->employee = Employee::factory()->for($this->company)->create(['branch_id' => $this->branch->id]);
        $this->service = Service::factory()->for($this->company)->create([
            'duration_minutes' => 60,
            'price' => 12000,
        ]);

        $this->employee->services()->attach($this->service->id);

        // Lunes a viernes 09-18
        foreach (range(1, 5) as $day) {
            $this->employee->workingHours()->create([
                'company_id' => $this->company->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '18:00',
            ]);
        }

        $this->slotStart = CarbonImmutable::now($this->company->timezone)
            ->next(CarbonImmutable::MONDAY)
            ->setTime(10, 0)
            ->utc();
    }

    private function booking(): BookingService
    {
        return app(BookingService::class);
    }

    public function test_books_an_appointment_with_price_and_customer_creation()
    {
        $appointment = $this->booking()->book(
            branch: $this->branch,
            service: $this->service,
            employee: $this->employee,
            customer: ['name' => 'Juan Pérez', 'email' => 'juan@example.com', 'phone' => '11-5555-0000'],
            startsAt: $this->slotStart,
            source: 'panel',
        );

        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame('12000.00', (string) $appointment->price);
        $this->assertSame('ARS', $appointment->currency);
        $this->assertTrue($appointment->starts_at->equalTo($this->slotStart));
        $this->assertSame($this->company->id, $appointment->company_id);
        $this->assertSame('juan@example.com', $appointment->customer->email);
    }

    public function test_reuses_existing_customer_by_email()
    {
        $existing = Customer::factory()->for($this->company)->create(['email' => 'juan@example.com']);

        $appointment = $this->booking()->book(
            branch: $this->branch,
            service: $this->service,
            employee: $this->employee,
            customer: ['name' => 'Juan Otro Nombre', 'email' => 'juan@example.com'],
            startsAt: $this->slotStart,
        );

        $this->assertSame($existing->id, $appointment->customer_id);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_prevents_double_booking_of_the_same_slot()
    {
        $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $this->employee,
            customer: ['name' => 'A', 'email' => 'a@example.com'],
            startsAt: $this->slotStart,
        );

        $this->expectException(SlotUnavailableException::class);

        $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $this->employee,
            customer: ['name' => 'B', 'email' => 'b@example.com'],
            startsAt: $this->slotStart->addMinutes(30), // solapa 10:30-11:30
        );
    }

    public function test_rejects_bookings_outside_working_hours()
    {
        $this->expectException(SlotUnavailableException::class);

        $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $this->employee,
            customer: ['name' => 'A', 'email' => 'a@example.com'],
            startsAt: $this->slotStart->setTime(23, 0),
        );
    }

    public function test_assigns_required_resources_and_fails_when_exhausted()
    {
        $type = ResourceType::factory()->for($this->company)->create(['name' => 'Sala']);
        Resource::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'resource_type_id' => $type->id,
        ]);
        $this->service->requiredResourceTypes()->attach($type->id, ['quantity' => 1]);
        $this->service->refresh();

        $other = Employee::factory()->for($this->company)->create(['branch_id' => $this->branch->id]);
        $other->services()->attach($this->service->id);
        foreach (range(1, 5) as $day) {
            $other->workingHours()->create([
                'company_id' => $this->company->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '18:00',
            ]);
        }

        // Primera reserva toma la única sala
        $first = $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $this->employee,
            customer: ['name' => 'A', 'email' => 'a@example.com'],
            startsAt: $this->slotStart,
        );

        $this->assertCount(1, $first->resources);

        // Otro empleado, mismo horario: sin sala => falla
        $this->expectException(SlotUnavailableException::class);

        $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $other,
            customer: ['name' => 'B', 'email' => 'b@example.com'],
            startsAt: $this->slotStart,
        );
    }

    public function test_group_service_allows_joins_until_capacity()
    {
        $group = Service::factory()->for($this->company)->create([
            'duration_minutes' => 60,
            'max_capacity' => 2,
        ]);
        $this->employee->services()->attach($group->id);

        $book = fn (string $email) => $this->booking()->book(
            branch: $this->branch, service: $group, employee: $this->employee,
            customer: ['name' => $email, 'email' => $email],
            startsAt: $this->slotStart,
        );

        $book('uno@example.com');
        $second = $book('dos@example.com');

        $this->assertSame('confirmed', $second->status);

        // Tercer cupo: capacidad completa
        $this->expectException(SlotUnavailableException::class);
        $book('tres@example.com');
    }

    public function test_rejects_cross_company_entities()
    {
        $foreign = Company::factory()->create();
        $foreignEmployee = Employee::factory()->for($foreign)->recycle($foreign)->create();

        $this->expectException(InvalidArgumentException::class);

        $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $foreignEmployee,
            customer: ['name' => 'X', 'email' => 'x@example.com'],
            startsAt: $this->slotStart,
        );
    }

    public function test_cancel_frees_the_slot_and_promotes_waitlist()
    {
        $appointment = $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $this->employee,
            customer: ['name' => 'A', 'email' => 'a@example.com'],
            startsAt: $this->slotStart,
        );

        $entry = WaitlistEntry::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'service_id' => $this->service->id,
            'employee_id' => null,
            'desired_from' => $this->slotStart->subHour(),
            'desired_to' => $this->slotStart->addHours(3),
            'status' => 'waiting',
        ]);

        $this->booking()->cancel($appointment, 'Cliente avisó');

        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertNotNull($appointment->fresh()->cancelled_at);
        $this->assertSame('notified', $entry->fresh()->status);

        // El slot vuelve a estar disponible
        $again = $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $this->employee,
            customer: ['name' => 'B', 'email' => 'b@example.com'],
            startsAt: $this->slotStart,
        );

        $this->assertSame('confirmed', $again->status);
    }

    public function test_reschedule_creates_linked_appointment_and_cancels_original()
    {
        $original = $this->booking()->book(
            branch: $this->branch, service: $this->service, employee: $this->employee,
            customer: ['name' => 'A', 'email' => 'a@example.com'],
            startsAt: $this->slotStart,
        );

        // Mover 30' (solapa consigo mismo: debe funcionar igual)
        $new = $this->booking()->reschedule($original, $this->slotStart->addMinutes(30));

        $this->assertSame('cancelled', $original->fresh()->status);
        $this->assertSame($original->id, $new->rescheduled_from_id);
        $this->assertTrue($new->starts_at->equalTo($this->slotStart->addMinutes(30)));
        $this->assertSame($original->customer_id, $new->customer_id);
    }
}
