<?php

namespace Tests\Feature\Booking;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeOffEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->company = Company::factory()->create();
        app(CurrentCompany::class)->set($this->company);

        $branch = Branch::factory()->for($this->company)->create();
        $this->employee = Employee::factory()->for($this->company)->create(['branch_id' => $branch->id]);

        $this->owner = User::factory()->create();
        $this->owner->companies()->attach($this->company);
        setPermissionsTeamId($this->company->id);
        $this->owner->assignRole('owner');
    }

    public function test_owner_can_block_time_for_an_employee()
    {
        $this->actingAs($this->owner)
            ->post(route('time-off.store'), [
                'employee_id' => $this->employee->id,
                'starts_at' => now()->addDay()->format('Y-m-d 09:00'),
                'ends_at' => now()->addDay()->format('Y-m-d 13:00'),
                'type' => 'block',
                'reason' => 'Trámite personal',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('time_off', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'type' => 'block',
            'reason' => 'Trámite personal',
        ]);
    }

    public function test_end_must_be_after_start()
    {
        $this->actingAs($this->owner)
            ->post(route('time-off.store'), [
                'employee_id' => $this->employee->id,
                'starts_at' => now()->addDay()->format('Y-m-d 13:00'),
                'ends_at' => now()->addDay()->format('Y-m-d 09:00'),
                'type' => 'block',
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_cannot_block_time_for_a_foreign_employee()
    {
        $foreign = Company::factory()->create();
        $foreignEmployee = Employee::factory()->for($foreign)->recycle($foreign)->create();

        $this->actingAs($this->owner)
            ->post(route('time-off.store'), [
                'employee_id' => $foreignEmployee->id,
                'starts_at' => now()->addDay()->format('Y-m-d 09:00'),
                'ends_at' => now()->addDay()->format('Y-m-d 13:00'),
                'type' => 'block',
            ])
            ->assertNotFound();
    }

    public function test_owner_can_delete_a_block()
    {
        $timeOff = TimeOff::factory()->for($this->company)->create([
            'employee_id' => $this->employee->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('time-off.destroy', $timeOff))
            ->assertRedirect();

        $this->assertDatabaseMissing('time_off', ['id' => $timeOff->id]);
    }

    public function test_cannot_delete_a_foreign_block()
    {
        $foreign = Company::factory()->create();
        $foreignTimeOff = TimeOff::factory()->for($foreign)->recycle($foreign)->create();

        $this->actingAs($this->owner)
            ->delete(route('time-off.destroy', $foreignTimeOff))
            ->assertNotFound();

        $this->assertDatabaseHas('time_off', ['id' => $foreignTimeOff->id]);
    }
}
