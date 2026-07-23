<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Gestión del turno SIN login, vía links firmados enviados por email.
 * El uuid es público (no adivinable) y la firma evita manipulación.
 * Sin tenant en el request: el CompanyScope no aplica (no hay empresa
 * activa) y el turno se resuelve por uuid global.
 */
class PublicBookingController extends Controller
{
    public function show(Appointment $appointment): View
    {
        $appointment->load(['service', 'employee', 'branch', 'customer', 'company']);

        return view('booking.manage', [
            'appointment' => $appointment,
            'tz' => $appointment->branch->effectiveTimezone(),
            'cancelUrl' => $appointment->isActive()
                ? URL::signedRoute('booking.cancel', ['appointment' => $appointment->uuid])
                : null,
        ]);
    }

    public function cancel(Appointment $appointment, BookingService $booking): RedirectResponse
    {
        if ($appointment->isActive()) {
            $booking->cancel($appointment, 'Cancelado por el cliente');
        }

        return redirect()->to(
            URL::signedRoute('booking.manage', ['appointment' => $appointment->uuid]),
        )->with('status', 'Tu turno fue cancelado.');
    }
}
