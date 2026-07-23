<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use App\Models\TimeOff;
use App\Services\AvailabilityService;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private Employee $employee;

    private Service $service;

    private CarbonImmutable $tuesday;

    protected function setUp(): void
    {
        parent::setUp();

        // Slot step de 30' para aserciones legibles
        $this->company = Company::factory()->create([
            'timezone' => 'America/Argentina/Buenos_Aires',
            'settings' => ['slot_step' => 30],
        ]);

        app(CurrentCompany::class)->set($this->company);

        $this->branch = Branch::factory()->for($this->company)->create();
        $this->employee = Employee::factory()->for($this->company)->create(['branch_id' => $this->branch->id]);
        $this->service = Service::factory()->for($this->company)->create(['duration_minutes' => 60]);

        $this->employee->services()->attach($this->service->id);

        // Martes 09-13 y 14-18 (turno partido, hora local de la sucursal)
        foreach ([['09:00', '13:00'], ['14:00', '18:00']] as [$from, $to]) {
            $this->employee->workingHours()->create([
                'company_id' => $this->company->id,
                'day_of_week' => 2,
                'start_time' => $from,
                'end_time' => $to,
            ]);
        }

        $this->tuesday = CarbonImmutable::now($this->company->timezone)
            ->next(CarbonImmutable::TUESDAY)
            ->startOfDay();
    }

    private function slots(?Employee $employee = null): Collection
    {
        return app(AvailabilityService::class)->slotsFor(
            $this->branch,
            $this->service,
            $employee ?? $this->employee,
            $this->tuesday,
            $this->tuesday,
        );
    }

    private function localTimes(Collection $slots): array
    {
        return $slots
            ->map(fn (array $slot) => $slot['starts_at']->setTimezone($this->company->timezone)->format('H:i'))
            ->all();
    }

    public function test_generates_slots_within_working_hours()
    {
        $times = $this->localTimes($this->slots());

        // Mañana: 09:00-12:00 (el servicio de 60' debe terminar a las 13)
        // Tarde: 14:00-17:00
        $this->assertSame([
            '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00',
        ], $times);
    }

    public function test_no_slots_on_days_without_working_hours()
    {
        $wednesday = $this->tuesday->addDay();

        $slots = app(AvailabilityService::class)->slotsFor(
            $this->branch, $this->service, $this->employee, $wednesday, $wednesday,
        );

        $this->assertCount(0, $slots);
    }

    public function test_existing_appointments_and_buffers_block_slots()
    {
        // Servicio reservado 10:00-11:00 con 15' de buffer posterior
        $booked = Service::factory()->for($this->company)->create([
            'duration_minutes' => 60,
            'buffer_after_minutes' => 15,
        ]);

        $start = $this->tuesday->setTime(10, 0)->utc();

        Appointment::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'service_id' => $booked->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(60),
        ]);

        $times = $this->localTimes($this->slots());

        // Bloqueado 10:00-11:15 => la mañana queda 09:00, 11:15, 11:45
        $this->assertSame([
            '09:00', '11:15', '11:45',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00',
        ], $times);
    }

    public function test_vacation_removes_the_whole_day()
    {
        TimeOff::factory()->for($this->company)->create([
            'employee_id' => $this->employee->id,
            'starts_at' => $this->tuesday->utc(),
            'ends_at' => $this->tuesday->endOfDay()->utc(),
            'type' => 'vacation',
        ]);

        $this->assertCount(0, $this->slots());
    }

    public function test_branch_holiday_blocks_all_employees()
    {
        TimeOff::factory()->for($this->company)->create([
            'employee_id' => null,
            'branch_id' => $this->branch->id,
            'starts_at' => $this->tuesday->utc(),
            'ends_at' => $this->tuesday->endOfDay()->utc(),
            'type' => 'holiday',
        ]);

        $this->assertCount(0, $this->slots());
    }

    public function test_extra_availability_adds_slots_outside_working_hours()
    {
        // Domingo (sin horario) con disponibilidad extra 10-12
        $sunday = $this->tuesday->subDays(2);

        TimeOff::factory()->for($this->company)->create([
            'employee_id' => $this->employee->id,
            'starts_at' => $sunday->setTime(10, 0)->utc(),
            'ends_at' => $sunday->setTime(12, 0)->utc(),
            'type' => 'extra',
        ]);

        $slots = app(AvailabilityService::class)->slotsFor(
            $this->branch, $this->service, $this->employee, $sunday, $sunday,
        );

        $this->assertSame(['10:00', '10:30', '11:00'], $this->localTimes($slots));
    }

    public function test_required_resources_limit_slots_across_employees()
    {
        // Un único sillón; el servicio lo requiere
        $type = ResourceType::factory()->for($this->company)->create(['name' => 'Sillón']);
        Resource::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'resource_type_id' => $type->id,
        ]);
        $this->service->requiredResourceTypes()->attach($type->id, ['quantity' => 1]);

        // Otro empleado con el mismo skill y horario ocupa el sillón 10-11
        $other = Employee::factory()->for($this->company)->create(['branch_id' => $this->branch->id]);
        $other->services()->attach($this->service->id);

        $start = $this->tuesday->setTime(10, 0)->utc();

        $occupying = Appointment::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $other->id,
            'service_id' => $this->service->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(60),
        ]);
        $occupying->resources()->attach(Resource::query()->first()->id);

        $times = $this->localTimes($this->slots($this->employee));

        // Aunque el empleado está libre 10:00, el sillón está tomado:
        // desaparecen 09:30 (solapa 9:30-10:30), 10:00 y 10:30
        $this->assertNotContains('09:30', $times);
        $this->assertNotContains('10:00', $times);
        $this->assertNotContains('10:30', $times);
        $this->assertContains('09:00', $times);
        $this->assertContains('11:00', $times);
    }

    public function test_group_service_offers_remaining_capacity_at_same_start()
    {
        $group = Service::factory()->for($this->company)->create([
            'duration_minutes' => 60,
            'max_capacity' => 3,
        ]);
        $this->employee->services()->attach($group->id);

        $start = $this->tuesday->setTime(10, 0)->utc();

        Appointment::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'service_id' => $group->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(60),
        ]);

        $slots = app(AvailabilityService::class)->slotsFor(
            $this->branch, $group, $this->employee, $this->tuesday, $this->tuesday,
        );

        $times = $this->localTimes($slots);

        // 10:00 sigue ofrecido (queda cupo 2/3), pero 10:30 no (solapa)
        $this->assertContains('10:00', $times);
        $this->assertNotContains('10:30', $times);
    }
}
