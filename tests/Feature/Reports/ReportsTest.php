<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->company = Company::factory()->create();
        app(CurrentCompany::class)->set($this->company);

        $this->owner = User::factory()->create();
        $this->owner->companies()->attach($this->company);
        setPermissionsTeamId($this->company->id);
        $this->owner->assignRole('owner');
    }

    private function makeAppointments(): void
    {
        $branch = Branch::factory()->for($this->company)->create();
        $employee = Employee::factory()->for($this->company)->create(['branch_id' => $branch->id]);
        $service = Service::factory()->for($this->company)->create();
        $customer = Customer::factory()->for($this->company)->create();

        $base = CarbonImmutable::now($this->company->timezone)->startOfMonth()->addDays(4)->setTime(10, 0)->utc();

        // 2 confirmados de $1000, 1 cancelado, 1 no_show
        foreach ([0, 1] as $i) {
            Appointment::factory()->for($this->company)->create([
                'branch_id' => $branch->id,
                'employee_id' => $employee->id,
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'starts_at' => $base->addDays($i),
                'ends_at' => $base->addDays($i)->addHour(),
                'status' => 'confirmed',
                'price' => 1000,
            ]);
        }

        foreach (['cancelled', 'no_show'] as $i => $status) {
            Appointment::factory()->for($this->company)->create([
                'branch_id' => $branch->id,
                'employee_id' => $employee->id,
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'starts_at' => $base->addDays(2 + $i),
                'ends_at' => $base->addDays(2 + $i)->addHour(),
                'status' => $status,
                'price' => 1000,
                'cancelled_at' => $status === 'cancelled' ? now() : null,
            ]);
        }
    }

    public function test_reports_page_shows_month_totals()
    {
        $this->makeAppointments();

        $this->actingAs($this->owner)
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/Index')
                ->where('report.totals.appointments', 4)
                ->where('report.totals.billable', 2)
                ->where('report.totals.cancelled', 1)
                ->where('report.totals.no_show', 1)
                ->where('report.totals.revenue', 2000)
                ->where('report.totals.unique_customers', 1)
                ->where('report.totals.cancellation_rate', 25)
                ->has('report.services', 1)
                ->where('report.services.0.count', 2));
    }

    public function test_reports_are_scoped_to_the_active_company()
    {
        $this->makeAppointments();

        // Datos de otra empresa en el mismo rango: no deben sumar
        $foreign = Company::factory()->create();
        Appointment::factory()->for($foreign)->recycle($foreign)->count(3)->create([
            'starts_at' => CarbonImmutable::now()->startOfMonth()->addDays(5),
            'ends_at' => CarbonImmutable::now()->startOfMonth()->addDays(5)->addHour(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('reports'))
            ->assertInertia(fn ($page) => $page
                ->where('report.totals.appointments', 4));
    }

    public function test_staff_cannot_view_reports()
    {
        $staff = User::factory()->create();
        $staff->companies()->attach($this->company);
        setPermissionsTeamId($this->company->id);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('reports'))
            ->assertForbidden();
    }

    public function test_custom_range_filters_results()
    {
        $this->makeAppointments();

        // Rango en el pasado lejano: sin datos
        $this->actingAs($this->owner)
            ->get(route('reports', ['from' => '2020-01-01', 'to' => '2020-01-31']))
            ->assertInertia(fn ($page) => $page
                ->where('report.totals.appointments', 0)
                ->where('report.totals.revenue', 0));
    }
}
