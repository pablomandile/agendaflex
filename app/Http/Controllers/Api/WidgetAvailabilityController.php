<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetAvailabilityController extends Controller
{
    public function __invoke(Request $request, Company $company, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string'],
            'service' => ['required', 'uuid'],
            'employee' => ['nullable', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        // Referencias públicas (slug/uuid) resueltas con queries scopeadas
        $branch = Branch::query()->where('slug', $validated['branch'])->firstOrFail();
        $service = Service::query()->where('uuid', $validated['service'])->firstOrFail();
        $employee = isset($validated['employee'])
            ? Employee::query()->where('uuid', $validated['employee'])->firstOrFail()
            : null;

        $tz = $branch->effectiveTimezone();
        $date = CarbonImmutable::parse($validated['date'], $tz);

        // No ofrecer fechas pasadas
        if ($date->endOfDay()->isPast()) {
            return response()->json([]);
        }

        $slots = $availability->slotsFor($branch, $service, $employee, $date, $date);

        $employeeUuids = Employee::query()
            ->whereIn('id', $slots->pluck('employee_id')->unique())
            ->pluck('uuid', 'id');

        return response()->json(
            $slots->map(fn (array $slot) => [
                'starts_at' => $slot['starts_at']->toIso8601ZuluString(),
                'label' => $slot['starts_at']->setTimezone($tz)->format('H:i'),
                'employee_uuid' => $employeeUuids[$slot['employee_id']],
            ])->values(),
        );
    }
}
