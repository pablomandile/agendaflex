<?php

namespace Tests\Feature\Notifications;

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\WaitlistSlotAvailableMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Service;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentNotificationsTest extends TestCase
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

        Mail::fake();

        $this->company = Company::factory()->create();
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

        $this->slotStart = CarbonImmutable::now($this->company->timezone)
            ->next(CarbonImmutable::MONDAY)
            ->setTime(10, 0)
            ->utc();
    }

    private function book(string $email = 'cliente@example.com'): Appointment
    {
        return app(BookingService::class)->book(
            branch: $this->branch,
            service: $this->service,
            employee: $this->employee,
            customer: ['name' => 'Cliente', 'email' => $email],
            startsAt: $this->slotStart,
        );
    }

    public function test_booking_queues_confirmation_email_and_logs_it()
    {
        $this->book();

        Mail::assertQueued(
            AppointmentConfirmedMail::class,
            fn (AppointmentConfirmedMail $mail) => $mail->hasTo('cliente@example.com'),
        );

        $this->assertDatabaseHas('notification_logs', [
            'company_id' => $this->company->id,
            'type' => 'confirmation',
            'recipient' => 'cliente@example.com',
            'channel' => 'email',
        ]);
    }

    public function test_cancel_queues_cancellation_email()
    {
        $appointment = $this->book();

        app(BookingService::class)->cancel($appointment);

        Mail::assertQueued(AppointmentCancelledMail::class);

        $this->assertDatabaseHas('notification_logs', [
            'appointment_id' => $appointment->id,
            'type' => 'cancellation',
        ]);
    }

    public function test_reschedule_queues_reschedule_email_but_not_a_second_confirmation()
    {
        $appointment = $this->book();

        $new = app(BookingService::class)->reschedule(
            $appointment,
            $this->slotStart->addHours(2),
        );

        Mail::assertQueued(
            AppointmentRescheduledMail::class,
            fn (AppointmentRescheduledMail $mail) => $mail->appointment->is($new),
        );

        // Solo la confirmación del booking original, no una del reprogramado
        Mail::assertQueuedCount(2); // confirmed (original) + rescheduled

        $this->assertDatabaseHas('notification_logs', [
            'appointment_id' => $new->id,
            'type' => 'reschedule',
        ]);
    }

    public function test_cancel_notifies_first_matching_waitlist_entry()
    {
        $appointment = $this->book();

        $entry = WaitlistEntry::factory()->for($this->company)->create([
            'branch_id' => $this->branch->id,
            'service_id' => $this->service->id,
            'employee_id' => null,
            'customer_id' => Customer::factory()->for($this->company)->create([
                'email' => 'espera@example.com',
            ])->id,
            'desired_from' => $this->slotStart->subHour(),
            'desired_to' => $this->slotStart->addHours(3),
            'status' => 'waiting',
        ]);

        app(BookingService::class)->cancel($appointment);

        Mail::assertQueued(
            WaitlistSlotAvailableMail::class,
            fn (WaitlistSlotAvailableMail $mail) => $mail->hasTo('espera@example.com'),
        );

        $this->assertSame('notified', $entry->fresh()->status);
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'waitlist',
            'recipient' => 'espera@example.com',
        ]);
    }
}
