<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Slots disponibles para un servicio en una fecha (para el diálogo
     * de reserva del panel).
     */
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        // Resolución vía queries scopeadas: cross-tenant => 404
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $service = Service::query()->findOrFail($validated['service_id']);
        $employee = isset($validated['employee_id'])
            ? Employee::query()->findOrFail($validated['employee_id'])
            : null;

        $tz = app(CurrentCompany::class)->get()->timezone;
        $date = CarbonImmutable::parse($validated['date'], $tz);

        $slots = $availability->slotsFor($branch, $service, $employee, $date, $date);

        return response()->json(
            $slots->map(fn (array $slot) => [
                'starts_at' => $slot['starts_at']->toIso8601ZuluString(),
                'label' => $slot['starts_at']->setTimezone($tz)->format('H:i'),
                'employee_id' => $slot['employee_id'],
                'group' => $slot['group'] ?? false,
            ]),
        );
    }
}
