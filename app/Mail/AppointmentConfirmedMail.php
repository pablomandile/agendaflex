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

class AppointmentConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Turno confirmado — {$this->appointment->company->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointments.confirmed',
            with: [
                'appointment' => $this->appointment,
                'tz' => $this->appointment->branch->effectiveTimezone(),
                'manageUrl' => URL::signedRoute('booking.manage', [
                    'appointment' => $this->appointment->uuid,
                ]),
            ],
        );
    }
}
