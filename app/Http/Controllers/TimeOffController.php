<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TimeOff;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Bloqueos de agenda (vacaciones, ausencias, francos) desde el calendario.
 */
class TimeOffController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'type' => ['required', 'in:vacation,holiday,sick,block'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Query scopeada: empleado de otra empresa => 404
        $employee = Employee::query()->findOrFail($validated['employee_id']);

        $tz = app(CurrentCompany::class)->get()->timezone;
        $startsAt = CarbonImmutable::parse($validated['starts_at'], $tz)->utc();
        $endsAt = CarbonImmutable::parse($validated['ends_at'], $tz)->utc();

        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'El fin del bloqueo debe ser posterior al inicio.',
            ]);
        }

        TimeOff::query()->create([
            'employee_id' => $employee->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'type' => $validated['type'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return back();
    }

    public function destroy(TimeOff $timeOff): RedirectResponse
    {
        $timeOff->delete();

        return back();
    }
}
