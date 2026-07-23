<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class WidgetBookingController extends Controller
{
    public function __invoke(Request $request, Company $company, BookingService $booking): JsonResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string'],
            'service' => ['required', 'uuid'],
            'employee' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $branch = Branch::query()->where('slug', $validated['branch'])->firstOrFail();
        $service = Service::query()->where('uuid', $validated['service'])->firstOrFail();
        $employee = Employee::query()->where('uuid', $validated['employee'])->firstOrFail();

        try {
            $appointment = $booking->book(
                branch: $branch,
                service: $service,
                employee: $employee,
                customer: [
                    'name' => $validated['customer']['name'],
                    'email' => $validated['customer']['email'],
                    'phone' => $validated['customer']['phone'] ?? null,
                ],
                startsAt: CarbonImmutable::parse($validated['starts_at']),
                source: 'widget',
                notes: $validated['notes'] ?? null,
            );
        } catch (SlotUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $tz = $branch->effectiveTimezone();

        return response()->json([
            'booking' => [
                'uuid' => $appointment->uuid,
                'status' => $appointment->status,
                'starts_at' => $appointment->starts_at->toIso8601ZuluString(),
                'local_time' => $appointment->starts_at->setTimezone($tz)->format('d/m/Y H:i'),
                'service' => $service->name,
                'employee' => $employee->name,
                'branch' => $branch->name,
                'price' => (float) $appointment->price,
                'currency' => $appointment->currency,
            ],
            // Link firmado para gestionar el turno sin login (se envía
            // también en el email de confirmación, Etapa 7)
            'manage_url' => URL::signedRoute('booking.manage', ['appointment' => $appointment->uuid]),
        ], 201);
    }
}
