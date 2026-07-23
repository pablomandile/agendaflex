<?php

namespace Tests\Feature\Domain;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use App\Tenancy\CurrentCompany;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_models_generate_public_uuids()
    {
        $company = Company::factory()->create();

        $service = Service::factory()->for($company)->create();
        $appointment = Appointment::factory()
            ->for($company)
            ->recycle($company)
            ->create();

        $this->assertNotEmpty($service->uuid);
        $this->assertNotEmpty($appointment->uuid);
        // La PK sigue siendo autoincremental (el uuid es solo público)
        $this->assertIsInt($service->id);
    }

    public function test_services_are_isolated_between_companies()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Service::factory()->for($companyA)->count(2)->create();
        Service::factory()->for($companyB)->count(5)->create();

        app(CurrentCompany::class)->set($companyA);

        $this->assertSame(2, Service::query()->count());
    }

    public function test_employee_skills_support_custom_overrides()
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->for($company)->recycle($company)->create();
        $service = Service::factory()->for($company)->create(['duration_minutes' => 60]);

        $employee->services()->attach($service->id, [
            'custom_duration_minutes' => 45,
            'custom_price' => 9900,
        ]);

        $pivot = $employee->services()->first()->pivot;

        $this->assertSame(45, (int) $pivot->custom_duration_minutes);
        $this->assertSame(9900.0, (float) $pivot->custom_price);
    }

    public function test_service_declares_required_resource_types_with_quantity()
    {
        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create();
        $room = ResourceType::factory()->for($company)->create(['name' => 'Sala']);

        $service->requiredResourceTypes()->attach($room->id, ['quantity' => 2]);

        $required = $service->requiredResourceTypes()->first();

        $this->assertSame('Sala', $required->name);
        $this->assertSame(2, (int) $required->pivot->quantity);
    }

    public function test_appointment_occupies_resources()
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $type = ResourceType::factory()->for($company)->create();
        $resource = Resource::factory()
            ->for($company)
            ->for($branch)
            ->create(['resource_type_id' => $type->id]);

        $appointment = Appointment::factory()
            ->for($company)
            ->recycle($company)
            ->create(['branch_id' => $branch->id]);

        $appointment->resources()->attach($resource->id);

        $this->assertTrue($appointment->resources()->whereKey($resource->id)->exists());
        $this->assertTrue($resource->appointments()->whereKey($appointment->id)->exists());
    }

    public function test_total_duration_includes_buffers()
    {
        $company = Company::factory()->create();
        $service = Service::factory()->for($company)->create([
            'duration_minutes' => 60,
            'buffer_before_minutes' => 10,
            'buffer_after_minutes' => 15,
        ]);

        $this->assertSame(85, $service->totalDurationMinutes());
    }

    public function test_appointment_active_status_helper()
    {
        $company = Company::factory()->create();

        $confirmed = Appointment::factory()->for($company)->recycle($company)->create();
        $cancelled = Appointment::factory()->for($company)->recycle($company)->cancelled()->create();

        $this->assertTrue($confirmed->isActive());
        $this->assertFalse($cancelled->isActive());
    }

    public function test_demo_seeder_creates_a_complete_company()
    {
        $this->seed(DemoSeeder::class);

        $company = Company::query()->where('slug', 'estudio-norte')->firstOrFail();
        app(CurrentCompany::class)->set($company);

        $this->assertSame(2, Branch::query()->count());
        $this->assertSame(5, Service::query()->count());
        $this->assertSame(3, Employee::query()->count());
        $this->assertSame(5, Resource::query()->count());
        $this->assertGreaterThan(0, Appointment::query()->count());

        // Cada empleado tiene horario cargado (turno partido, mar-sáb)
        Employee::query()->each(function (Employee $employee) {
            $this->assertSame(10, $employee->workingHours()->count());
        });
    }
}
