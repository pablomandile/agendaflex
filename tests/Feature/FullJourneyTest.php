<?php

namespace Tests\Feature;

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Recorrido end-to-end del MVP completo, con dos empresas conviviendo:
 * configurar negocio → reservar por el widget (API pública) → email de
 * confirmación → aparece en el panel → cancelar por link firmado →
 * email de cancelación → el reporte lo refleja → la otra empresa nunca
 * ve nada.
 */
class FullJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_complete_booking_lifecycle_across_two_companies()
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        // ── 1. Dos empresas configuradas ─────────────────────────────
        [$salon, $salonOwner, $salonEmployee] = $this->makeCompany('Salón Uno', 'salon-uno');
        [$spa] = $this->makeCompany('Spa Dos', 'spa-dos');

        // ── 2. Cliente reserva por el widget (API pública) ───────────
        $service = Service::query()->withoutGlobalScopes()
            ->where('company_id', $salon->id)->firstOrFail();
        $branch = Branch::query()->withoutGlobalScopes()
            ->where('company_id', $salon->id)->firstOrFail();

        $slotStart = CarbonImmutable::now($salon->timezone)
            ->next(CarbonImmutable::WEDNESDAY)
            ->setTime(10, 0)
            ->utc();

        $booking = $this->postJson("/api/v1/{$salon->slug}/bookings", [
            'branch' => $branch->slug,
            'service' => $service->uuid,
            'employee' => $salonEmployee->uuid,
            'starts_at' => $slotStart->toIso8601ZuluString(),
            'customer' => [
                'name' => 'Ana Cliente',
                'email' => 'ana@example.com',
                'phone' => '11-5555-1234',
            ],
        ], ['X-Public-Key' => $salon->public_key]);

        $booking->assertCreated();
        $manageUrl = $booking->json('manage_url');

        // ── 3. Email de confirmación con link firmado ────────────────
        Mail::assertQueued(
            AppointmentConfirmedMail::class,
            fn (AppointmentConfirmedMail $mail) => $mail->hasTo('ana@example.com'),
        );

        // ── 4. El turno aparece en el calendario del panel del dueño ─
        $events = $this->actingAs($salonOwner)->getJson(route('calendar.events', [
            'start' => $slotStart->subDay()->toDateString(),
            'end' => $slotStart->addDay()->toDateString(),
        ]));

        $events->assertOk();
        $this->assertCount(1, $events->json());
        $this->assertStringContainsString('Ana Cliente', $events->json('0.title'));

        // ── 5. La cliente gestiona sin login: ve el detalle con el link
        //       firmado del email y cancela desde ahí ──────────────────
        $this->get($manageUrl)->assertOk()->assertSee('Ana Cliente');

        $appointment = Appointment::query()->withoutGlobalScopes()
            ->where('company_id', $salon->id)->firstOrFail();

        $this->post(URL::signedRoute(
            'booking.cancel',
            ['appointment' => $appointment->uuid],
        ))->assertRedirect();

        $this->assertSame('cancelled', $appointment->fresh()->status);

        Mail::assertQueued(
            AppointmentCancelledMail::class,
            fn (AppointmentCancelledMail $mail) => $mail->hasTo('ana@example.com'),
        );

        // ── 6. El reporte del dueño refleja la cancelación ───────────
        $this->actingAs($salonOwner)
            ->get(route('reports'))
            ->assertInertia(fn ($page) => $page
                ->where('report.totals.appointments', 1)
                ->where('report.totals.cancelled', 1)
                ->where('report.totals.billable', 0));

        // ── 7. Aislamiento: la otra empresa nunca vio nada ───────────
        app(CurrentCompany::class)->set($spa);
        $this->assertSame(0, Appointment::query()->count());
        $this->assertSame(0, Customer::query()->count());
        app(CurrentCompany::class)->set(null);
    }

    /**
     * @return array{0: Company, 1: User, 2: Employee}
     */
    private function makeCompany(string $name, string $slug): array
    {
        $company = Company::factory()->create([
            'name' => $name,
            'slug' => $slug,
            'timezone' => 'America/Argentina/Buenos_Aires',
        ]);

        app(CurrentCompany::class)->set($company);

        $branch = Branch::factory()->for($company)->create();
        $employee = Employee::factory()->for($company)->create(['branch_id' => $branch->id]);
        $service = Service::factory()->for($company)->create(['duration_minutes' => 60]);
        $employee->services()->attach($service->id);

        // Recurso requerido: 1 sala
        $type = ResourceType::factory()->for($company)->create(['name' => 'Sala']);
        Resource::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'resource_type_id' => $type->id,
        ]);
        $service->requiredResourceTypes()->attach($type->id, ['quantity' => 1]);

        foreach (range(1, 5) as $day) {
            $employee->workingHours()->create([
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '18:00',
            ]);
        }

        $owner = User::factory()->create();
        $owner->companies()->attach($company);
        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');

        app(CurrentCompany::class)->set(null);

        return [$company, $owner, $employee];
    }
}
