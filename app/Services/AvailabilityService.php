<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Resource;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cálculo de slots disponibles: horario del empleado (con vigencias y
 * turnos partidos) - excepciones (vacaciones/feriados) - turnos tomados
 * (expandidos por buffers) + disponibilidad extra, con chequeo de
 * recursos requeridos y cupos grupales.
 *
 * Todos los instantes se manejan en UTC; las horas de pared se
 * construyen en la zona horaria de la sucursal.
 */
class AvailabilityService
{
    public const DEFAULT_SLOT_STEP_MINUTES = 15;

    /**
     * Margen de búsqueda alrededor de la ventana para captar turnos
     * cuyos buffers se extienden hacia adentro (minutos).
     */
    public const BUFFER_SEARCH_MARGIN_MINUTES = 240;

    /**
     * Slots disponibles para un servicio en una sucursal entre dos fechas
     * (interpretadas en la zona horaria de la sucursal).
     *
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, employee_id: int, group?: bool}>
     */
    public function slotsFor(
        Branch $branch,
        Service $service,
        ?Employee $employee,
        CarbonImmutable $fromDate,
        CarbonImmutable $toDate,
    ): Collection {
        $tz = $branch->effectiveTimezone();
        $step = (int) data_get($branch->company->settings, 'slot_step', self::DEFAULT_SLOT_STEP_MINUTES);

        $candidates = $this->candidateEmployees($branch, $service, $employee);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $windowStart = $fromDate->setTimezone($tz)->startOfDay();
        $windowEnd = $toDate->setTimezone($tz)->endOfDay();

        $timeOff = $this->timeOffFor($branch, $candidates, $windowStart->utc(), $windowEnd->utc());
        $appointments = $this->activeAppointmentsFor($candidates, $windowStart->utc(), $windowEnd->utc());
        $resourcePool = $this->resourcePoolFor($branch, $service);
        $resourceUsage = $resourcePool === null
            ? collect()
            : $this->resourceUsageFor($branch, $windowStart->utc(), $windowEnd->utc());

        $slots = collect();

        for ($day = $windowStart; $day->lte($windowEnd); $day = $day->addDay()) {
            foreach ($candidates as $candidate) {
                $slots->push(...$this->employeeSlotsForDay($service, $candidate, $day, $step, $timeOff, $appointments));
            }
        }

        if ($resourcePool !== null) {
            $slots = $slots->filter(
                fn (array $slot) => $this->resourcesAvailable($service, $resourcePool, $resourceUsage, $slot),
            );
        }

        return $slots
            ->filter(fn (array $slot) => $slot['starts_at']->isFuture())
            ->sortBy(fn (array $slot) => [$slot['starts_at']->timestamp, $slot['employee_id']])
            ->values();
    }

    /**
     * ¿Está disponible este inicio exacto para el empleado dado?
     * (Usado como re-chequeo dentro de la transacción de booking.)
     */
    public function isAvailable(Branch $branch, Service $service, Employee $employee, CarbonImmutable $startsAtUtc): bool
    {
        $date = $startsAtUtc->setTimezone($branch->effectiveTimezone());

        return $this->slotsFor($branch, $service, $employee, $date, $date)
            ->contains(fn (array $slot) => $slot['starts_at']->equalTo($startsAtUtc));
    }

    /**
     * Empleados activos de la sucursal con el skill del servicio.
     *
     * @return Collection<int, Employee>
     */
    private function candidateEmployees(Branch $branch, Service $service, ?Employee $employee): Collection
    {
        return $service->employees()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->when($employee, fn ($query) => $query->whereKey($employee->id))
            ->with('workingHours')
            ->get();
    }

    private function timeOffFor(Branch $branch, Collection $employees, CarbonImmutable $startUtc, CarbonImmutable $endUtc): Collection
    {
        return TimeOff::query()
            ->where(function ($query) use ($employees, $branch) {
                $query->whereIn('employee_id', $employees->modelKeys())
                    ->orWhere(function ($query) use ($branch) {
                        // Feriado de sucursal o cierre de toda la empresa
                        $query->whereNull('employee_id')
                            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id));
                    });
            })
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->get();
    }

    private function activeAppointmentsFor(Collection $employees, CarbonImmutable $startUtc, CarbonImmutable $endUtc): Collection
    {
        return Appointment::query()
            ->with('service')
            ->whereIn('employee_id', $employees->modelKeys())
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->where('starts_at', '<', $endUtc->addMinutes(self::BUFFER_SEARCH_MARGIN_MINUTES))
            ->where('ends_at', '>', $startUtc->subMinutes(self::BUFFER_SEARCH_MARGIN_MINUTES))
            ->get();
    }

    /**
     * Recursos activos de la sucursal agrupados por tipo, o null si el
     * servicio no requiere recursos.
     */
    private function resourcePoolFor(Branch $branch, Service $service): ?Collection
    {
        if ($service->requiredResourceTypes->isEmpty()) {
            return null;
        }

        return Resource::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereIn('resource_type_id', $service->requiredResourceTypes->modelKeys())
            ->get()
            ->groupBy('resource_type_id');
    }

    /**
     * Turnos activos de la sucursal (de cualquier empleado) con sus
     * recursos, para computar ocupación de recursos por franja.
     */
    private function resourceUsageFor(Branch $branch, CarbonImmutable $startUtc, CarbonImmutable $endUtc): Collection
    {
        return Appointment::query()
            ->with('resources')
            ->where('branch_id', $branch->id)
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->get();
    }

    /**
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, employee_id: int, group?: bool}>
     */
    private function employeeSlotsForDay(
        Service $service,
        Employee $employee,
        CarbonImmutable $day,
        int $step,
        Collection $timeOff,
        Collection $appointments,
    ): array {
        // 1. Ventanas de trabajo del día (turnos partidos = varias filas)
        $windows = $employee->workingHours
            ->filter(fn (WorkingHour $wh) => $wh->day_of_week === $day->dayOfWeek
                && ($wh->effective_from === null || $wh->effective_from->startOfDay()->lte($day))
                && ($wh->effective_to === null || $wh->effective_to->endOfDay()->gte($day)))
            ->map(fn (WorkingHour $wh) => [
                'start' => $day->setTimeFromTimeString($wh->start_time)->utc(),
                'end' => $day->setTimeFromTimeString($wh->end_time)->utc(),
            ]);

        // Disponibilidad extra puntual (suma agenda)
        $dayStartUtc = $day->startOfDay()->utc();
        $dayEndUtc = $day->endOfDay()->utc();

        $extra = $timeOff->filter(
            fn (TimeOff $off) => $off->isExtraAvailability()
                && $off->employee_id === $employee->id
                && $off->starts_at->lt($dayEndUtc)
                && $off->ends_at->gt($dayStartUtc),
        )->map(fn (TimeOff $off) => ['start' => $off->starts_at, 'end' => $off->ends_at]);

        $windows = $windows->concat($extra)->values();

        if ($windows->isEmpty()) {
            return [];
        }

        // 2. Bloqueos: excepciones aplicables + turnos existentes con buffers
        $blocks = $timeOff->filter(
            fn (TimeOff $off) => ! $off->isExtraAvailability()
                && ($off->employee_id === $employee->id || $off->employee_id === null),
        )->map(fn (TimeOff $off) => ['start' => $off->starts_at, 'end' => $off->ends_at]);

        [$busy, $groupStarts] = $this->employeeBusyBlocks($service, $employee, $appointments);

        $free = $this->subtract($windows->all(), $blocks->concat($busy)->all());

        // 3. Starts candidatos sobre las ventanas libres. El bloque completo
        //    (buffer antes + servicio + buffer después) debe entrar entero.
        $duration = $service->duration_minutes;
        $before = $service->buffer_before_minutes;
        $after = $service->buffer_after_minutes;

        $slots = [];

        foreach ($free as $window) {
            $start = $window['start']->addMinutes($before);

            while ($start->addMinutes($duration + $after)->lte($window['end'])) {
                $slots[$start->timestamp] = [
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($duration),
                    'employee_id' => $employee->id,
                ];

                $start = $start->addMinutes($step);
            }
        }

        // 4. Cupos grupales: el inicio exacto de un grupo con capacidad
        //    restante se ofrece aunque la franja esté ocupada.
        foreach ($groupStarts as $groupStart) {
            $slots[$groupStart->timestamp] = [
                'starts_at' => $groupStart,
                'ends_at' => $groupStart->addMinutes($duration),
                'employee_id' => $employee->id,
                'group' => true,
            ];
        }

        return array_values($slots);
    }

    /**
     * Bloques ocupados del empleado (buffers incluidos) y, si el servicio
     * es grupal, los inicios de grupos con cupo restante.
     *
     * @return array{0: Collection, 1: array<int, CarbonImmutable>}
     */
    private function employeeBusyBlocks(Service $service, Employee $employee, Collection $appointments): array
    {
        $own = $appointments->where('employee_id', $employee->id);

        $busy = $own->map(fn (Appointment $appointment) => [
            'start' => $appointment->starts_at->subMinutes($appointment->service->buffer_before_minutes),
            'end' => $appointment->ends_at->addMinutes($appointment->service->buffer_after_minutes),
        ])->values();

        $groupStarts = [];

        if ($service->max_capacity > 1) {
            $groupStarts = $own
                ->where('service_id', $service->id)
                ->groupBy(fn (Appointment $appointment) => $appointment->starts_at->timestamp)
                ->filter(fn (Collection $group) => $group->count() < $service->max_capacity)
                ->map(fn (Collection $group) => $group->first()->starts_at)
                ->values()
                ->all();
        }

        return [$busy, $groupStarts];
    }

    /**
     * Chequeo de recursos: quedan >= quantity recursos libres de cada tipo
     * requerido durante la franja del slot. Los slots grupales comparten
     * los recursos ya asignados al grupo.
     */
    private function resourcesAvailable(Service $service, Collection $pool, Collection $usage, array $slot): bool
    {
        if ($slot['group'] ?? false) {
            return true;
        }

        $overlapping = $usage->filter(
            fn (Appointment $appointment) => $appointment->starts_at->lt($slot['ends_at'])
                && $appointment->ends_at->gt($slot['starts_at']),
        );

        foreach ($service->requiredResourceTypes as $type) {
            $total = $pool->get($type->id, collect())->count();

            $used = $overlapping
                ->flatMap(fn (Appointment $appointment) => $appointment->resources)
                ->where('resource_type_id', $type->id)
                ->unique('id')
                ->count();

            if ($total - $used < (int) $type->pivot->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resta intervalos bloqueados de un conjunto de ventanas.
     *
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $windows
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $blocks
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function subtract(array $windows, array $blocks): array
    {
        $result = [];

        foreach ($windows as $window) {
            $pieces = [$window];

            foreach ($blocks as $block) {
                $next = [];

                foreach ($pieces as $piece) {
                    // Sin solape: la pieza queda entera
                    if ($block['end']->lte($piece['start']) || $block['start']->gte($piece['end'])) {
                        $next[] = $piece;

                        continue;
                    }

                    if ($block['start']->gt($piece['start'])) {
                        $next[] = ['start' => $piece['start'], 'end' => $block['start']];
                    }

                    if ($block['end']->lt($piece['end'])) {
                        $next[] = ['start' => $block['end'], 'end' => $piece['end']];
                    }
                }

                $pieces = $next;
            }

            $result = array_merge($result, $pieces);
        }

        return $result;
    }
}
