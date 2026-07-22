<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_current_company()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Branch::factory()->for($companyA)->count(2)->create();
        Branch::factory()->for($companyB)->count(3)->create();

        app(CurrentCompany::class)->set($companyA);
        $this->assertSame(2, Branch::query()->count());

        app(CurrentCompany::class)->set($companyB);
        $this->assertSame(3, Branch::query()->count());
    }

    public function test_creating_a_model_auto_assigns_the_current_company()
    {
        $company = Company::factory()->create();
        app(CurrentCompany::class)->set($company);

        $branch = Branch::query()->create(['name' => 'Sucursal Centro']);

        $this->assertSame($company->id, $branch->company_id);
    }

    public function test_without_scope_sees_all_companies_data()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Branch::factory()->for($companyA)->create();
        Branch::factory()->for($companyB)->create();

        $current = app(CurrentCompany::class);
        $current->set($companyA);

        $total = $current->withoutScope(fn () => Branch::query()->count());

        $this->assertSame(2, $total);
        // Al salir del callback, el scope vuelve a aplicarse
        $this->assertSame(1, Branch::query()->count());
    }

    public function test_session_middleware_sets_current_company_for_member()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame($company->id, app(CurrentCompany::class)->id());
        $this->assertSame($company->id, session('current_company_id'));
    }

    public function test_session_company_must_be_a_real_membership()
    {
        $own = Company::factory()->create();
        $foreign = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($own);

        // Sesión apuntando a una empresa ajena: se corrige a la propia
        $this->actingAs($user)
            ->withSession(['current_company_id' => $foreign->id])
            ->get(route('dashboard'));

        $this->assertSame($own->id, app(CurrentCompany::class)->id());
    }

    public function test_company_switch_requires_membership()
    {
        $own = Company::factory()->create();
        $foreign = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($own);

        $this->actingAs($user)
            ->post(route('company.switch'), ['company_id' => $foreign->id])
            ->assertForbidden();
    }

    public function test_company_switch_changes_the_active_company()
    {
        $first = Company::factory()->create();
        $second = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach([$first->id, $second->id]);

        $this->actingAs($user)
            ->post(route('company.switch'), ['company_id' => $second->id])
            ->assertRedirect();

        $this->assertSame($second->id, session('current_company_id'));
    }
}
