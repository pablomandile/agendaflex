<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Se envía sobre el turno NUEVO (el original queda cancelado y enlazado
 * vía rescheduled_from_id).
 */
class AppointmentRescheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu turno fue reprogramado — {$this->appointment->company->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointments.rescheduled',
            with: [
                'appointment' => $this->appointment,
                'original' => $this->appointment->rescheduledFrom,
                'tz' => $this->appointment->branch->effectiveTimezone(),
                'manageUrl' => URL::signedRoute('booking.manage', [
                    'appointment' => $this->appointment->uuid,
                ]),
            ],
        );
    }
}
