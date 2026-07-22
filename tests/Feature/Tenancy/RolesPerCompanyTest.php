<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesPerCompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_user_can_have_different_roles_per_company()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach([$companyA->id, $companyB->id]);

        setPermissionsTeamId($companyA->id);
        $user->assignRole('owner');

        setPermissionsTeamId($companyB->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole('staff');

        // En A es owner
        setPermissionsTeamId($companyA->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($user->hasRole('owner'));
        $this->assertFalse($user->hasRole('staff'));
        $this->assertTrue($user->can('company.settings'));

        // En B es staff
        setPermissionsTeamId($companyB->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($user->hasRole('staff'));
        $this->assertFalse($user->hasRole('owner'));
        $this->assertTrue($user->can('appointments.create'));
        $this->assertFalse($user->can('company.settings'));
    }

    public function test_super_admin_bypasses_all_permission_checks()
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        // Sin rol ni empresa asignada: Gate::before lo autoriza igual
        $this->assertTrue($user->can('company.settings'));
        $this->assertTrue($user->can('reports.view'));
    }

    public function test_regular_user_without_roles_has_no_permissions()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company);

        setPermissionsTeamId($company->id);

        $this->assertFalse($user->can('company.settings'));
        $this->assertFalse($user->can('appointments.view'));
    }

    public function test_staff_permissions_are_limited()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company);

        setPermissionsTeamId($company->id);
        $user->assignRole('staff');

        $this->assertTrue($user->can('appointments.view'));
        $this->assertTrue($user->can('customers.manage'));
        $this->assertFalse($user->can('services.manage'));
        $this->assertFalse($user->can('reports.view'));
    }
}
