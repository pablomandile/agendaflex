<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Service;
use App\Models\TimeOff;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Página del calendario (vistas día/semana/mes).
     */
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('calendar/Index', [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->get(['id', 'name'])
                ->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name]),
            'employees' => Employee::query()
                ->where('is_active', true)
                ->with('services:services.id')
                ->get()
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'color' => $employee->color,
                    'branch_id' => $employee->branch_id,
                    'service_ids' => $employee->services->pluck('id')->values(),
                ]),
            'services' => Service::query()
                ->where('is_active', true)
                ->with('category:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => (float) $service->price,
                    'category' => $service->category?->name,
                ]),
            'timezone' => $company->timezone,
        ]);
    }

    /**
     * Eventos del rango visible en formato FullCalendar. Las fechas se
     * devuelven como hora local "naive" de la empresa (el panel asume
     * que el navegador opera en la zona horaria del negocio).
     */
    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $tz = app(CurrentCompany::class)->get()->timezone;
        $start = CarbonImmutable::parse($validated['start'], $tz)->utc();
        $end = CarbonImmutable::parse($validated['end'], $tz)->utc();

        $appointments = Appointment::query()
            ->with(['customer:id,name', 'service:id,name', 'employee:id,name,color'])
            ->where('status', '!=', 'cancelled')
            ->when($validated['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($validated['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => (string) $appointment->id,
                'title' => "{$appointment->customer->name} · {$appointment->service->name}",
                'start' => $appointment->starts_at->setTimezone($tz)->format('Y-m-d\TH:i:s'),
                'end' => $appointment->ends_at->setTimezone($tz)->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $appointment->employee->color,
                'borderColor' => $appointment->employee->color,
                'extendedProps' => [
                    'kind' => 'appointment',
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                    'customer_name' => $appointment->customer->name,
                    'service_id' => $appointment->service_id,
                    'service_name' => $appointment->service->name,
                    'employee_id' => $appointment->employee_id,
                    'employee_name' => $appointment->employee->name,
                ],
            ]);

        $timeOff = TimeOff::query()
            ->where('type', '!=', 'extra')
            ->when($validated['employee_id'] ?? null, fn ($query, $id) => $query
                ->where(fn ($q) => $q->where('employee_id', $id)->orWhereNull('employee_id')))
            ->when($validated['branch_id'] ?? null, fn ($query, $id) => $query
                ->where(fn ($q) => $q->where('branch_id', $id)->orWhereNull('branch_id')))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get()
            ->map(fn (TimeOff $off) => [
                'id' => 'off-'.$off->id,
                'start' => $off->starts_at->setTimezone($tz)->format('Y-m-d\TH:i:s'),
                'end' => $off->ends_at->setTimezone($tz)->format('Y-m-d\TH:i:s'),
                'display' => 'background',
                'color' => '#94a3b8',
                'extendedProps' => [
                    'kind' => 'time_off',
                    'time_off_id' => $off->id,
                    'type' => $off->type,
                    'reason' => $off->reason,
                    'employee_id' => $off->employee_id,
                ],
            ]);

        return response()->json($appointments->concat($timeOff)->values());
    }
}
