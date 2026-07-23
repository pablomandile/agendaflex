<?php

namespace Tests\Feature\Notifications;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeAppointment(Company $company, int $hoursFromNow, string $email): Appointment
    {
        app(CurrentCompany::class)->set($company);

        $appointment = Appointment::factory()
            ->for($company)
            ->recycle($company)
            ->create([
                'starts_at' => now()->addHours($hoursFromNow),
                'ends_at' => now()->addHours($hoursFromNow + 1),
                'customer_id' => Customer::factory()->for($company)->create([
                    'email' => $email,
                ])->id,
            ]);

        app(CurrentCompany::class)->set(null);

        return $appointment;
    }

    public function test_sends_reminders_only_for_upcoming_window_and_never_twice()
    {
        Mail::fake();

        $company = Company::factory()->create(); // reminder_hours default: 24

        $soon = $this->makeAppointment($company, 5, 'pronto@example.com');
        $this->makeAppointment($company, 48, 'lejos@example.com');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertQueued(
            AppointmentReminderMail::class,
            fn (AppointmentReminderMail $mail) => $mail->hasTo('pronto@example.com'),
        );
        Mail::assertNotQueued(
            AppointmentReminderMail::class,
            fn (AppointmentReminderMail $mail) => $mail->hasTo('lejos@example.com'),
        );

        $this->assertDatabaseHas('notification_logs', [
            'appointment_id' => $soon->id,
            'type' => 'reminder',
        ]);

        // Segunda corrida: no duplica (dedupe por notification_logs)
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertQueuedCount(1);
    }

    public function test_iterates_every_active_company_with_its_own_tenant_context()
    {
        Mail::fake();

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $suspended = Company::factory()->suspended()->create();

        $this->makeAppointment($companyA, 3, 'a@example.com');
        $this->makeAppointment($companyB, 3, 'b@example.com');
        $this->makeAppointment($suspended, 3, 'suspendida@example.com');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertQueued(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo('a@example.com'));
        Mail::assertQueued(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo('b@example.com'));
        // Las empresas suspendidas no envían nada
        Mail::assertNotQueued(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo('suspendida@example.com'));
    }

    public function test_respects_per_company_reminder_window()
    {
        Mail::fake();

        $company = Company::factory()->create([
            'settings' => ['reminder_hours' => 2],
        ]);

        $this->makeAppointment($company, 1, 'dentro@example.com');
        $this->makeAppointment($company, 5, 'fuera@example.com');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Mail::assertQueued(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo('dentro@example.com'));
        Mail::assertNotQueued(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo('fuera@example.com'));
    }
}
