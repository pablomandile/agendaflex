<?php

namespace App\Http\Controllers;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function __construct(private BookingService $booking) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'customer.id' => ['nullable', 'integer'],
            'customer.name' => ['required_without:customer.id', 'nullable', 'string', 'max:255'],
            'customer.email' => ['required_without:customer.id', 'nullable', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Queries scopeadas: ids de otra empresa => 404
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $service = Service::query()->findOrFail($validated['service_id']);
        $employee = Employee::query()->findOrFail($validated['employee_id']);

        $customer = isset($validated['customer']['id'])
            ? Customer::query()->findOrFail($validated['customer']['id'])
            : [
                'name' => $validated['customer']['name'],
                'email' => $validated['customer']['email'],
                'phone' => $validated['customer']['phone'] ?? null,
            ];

        try {
            $this->booking->book(
                branch: $branch,
                service: $service,
                employee: $employee,
                customer: $customer,
                startsAt: CarbonImmutable::parse($validated['starts_at']),
                source: 'panel',
                notes: $validated['notes'] ?? null,
            );
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return back();
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->booking->cancel($appointment, $validated['reason'] ?? null);
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['appointment' => $e->getMessage()]);
        }

        return back();
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $employee = isset($validated['employee_id'])
            ? Employee::query()->findOrFail($validated['employee_id'])
            : null;

        try {
            $this->booking->reschedule(
                $appointment,
                CarbonImmutable::parse($validated['starts_at']),
                $employee,
            );
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return back();
    }
}
