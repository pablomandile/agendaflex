<?php

namespace App\Services;

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentReminderMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\WaitlistSlotAvailableMail;
use App\Models\Appointment;
use App\Models\NotificationLog;
use App\Models\WaitlistEntry;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Encola los emails del ciclo de vida del turno y deja auditoría en
 * notification_logs. Llamar SIEMPRE después de commitear (nunca dentro
 * de una transacción: un rollback no debe dejar emails en la cola).
 */
class AppointmentNotifier
{
    public function confirmed(Appointment $appointment): void
    {
        $this->send($appointment, 'confirmation', new AppointmentConfirmedMail($appointment));
    }

    public function reminder(Appointment $appointment): void
    {
        $this->send($appointment, 'reminder', new AppointmentReminderMail($appointment));
    }

    public function cancelled(Appointment $appointment): void
    {
        $this->send($appointment, 'cancellation', new AppointmentCancelledMail($appointment));
    }

    public function rescheduled(Appointment $appointment): void
    {
        $this->send($appointment, 'reschedule', new AppointmentRescheduledMail($appointment));
    }

    public function waitlistSlotFreed(WaitlistEntry $entry): void
    {
        Mail::to($entry->customer->email)->queue(new WaitlistSlotAvailableMail($entry));

        $this->log($entry->company_id, null, 'waitlist', $entry->customer->email);
    }

    public function wasReminded(Appointment $appointment): bool
    {
        return NotificationLog::query()
            ->where('appointment_id', $appointment->id)
            ->where('type', 'reminder')
            ->exists();
    }

    private function send(Appointment $appointment, string $type, Mailable $mail): void
    {
        $recipient = $appointment->customer->email;

        Mail::to($recipient)->queue($mail);

        $this->log($appointment->company_id, $appointment->id, $type, $recipient);
    }

    /**
     * El log hereda la empresa del turno/entrada, NO del contexto: los
     * flujos públicos (link firmado de cancelación) corren sin tenant.
     */
    private function log(int $companyId, ?int $appointmentId, string $type, string $recipient): void
    {
        $log = new NotificationLog([
            'appointment_id' => $appointmentId,
            'channel' => 'email',
            'type' => $type,
            'recipient' => $recipient,
            'status' => 'queued',
        ]);

        $log->company_id = $companyId;
        $log->save();
    }
}
