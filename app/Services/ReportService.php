<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Employee;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reportes del negocio derivados de appointments (sin tablas extra).
 * Las queries corren scopeadas al tenant activo por el CompanyScope.
 *
 * "Ingresos" = suma de price de turnos confirmados/completados en el
 * rango (ingreso comprometido; sin módulo de pagos en el MVP).
 */
class ReportService
{
    /**
     * @return array{
     *   totals: array<string, int|float>,
     *   services: array<int, array<string, mixed>>,
     *   employees: array<int, array<string, mixed>>
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $appointments = Appointment::query()
            ->with(['service:id,name', 'employee:id,name,color'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get();

        $revenueStatuses = ['confirmed', 'completed'];
        $billable = $appointments->whereIn('status', $revenueStatuses);
        $cancelled = $appointments->where('status', 'cancelled');
        $noShow = $appointments->where('status', 'no_show');

        return [
            'totals' => [
                'appointments' => $appointments->count(),
                'billable' => $billable->count(),
                'cancelled' => $cancelled->count(),
                'no_show' => $noShow->count(),
                'revenue' => (float) $billable->sum('price'),
                'unique_customers' => $appointments->pluck('customer_id')->unique()->count(),
                'cancellation_rate' => $appointments->isEmpty()
                    ? 0.0
                    : round($cancelled->count() / $appointments->count() * 100, 1),
            ],
            'services' => $this->byService($billable),
            'employees' => $this->byEmployee($billable, $from, $to),
        ];
    }

    /**
     * Servicios más populares (por cantidad de turnos facturables).
     */
    private function byService(Collection $billable): array
    {
        return $billable
            ->groupBy('service_id')
            ->map(fn (Collection $group) => [
                'name' => $group->first()->service->name,
                'count' => $group->count(),
                'revenue' => (float) $group->sum('price'),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Rendimiento por empleado: turnos, ingresos y ocupación (minutos
     * reservados / minutos de agenda publicada en el rango).
     */
    private function byEmployee(Collection $billable, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $weekdayCounts = $this->weekdayCounts($from, $to);

        $workingMinutes = WorkingHour::query()
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $hours) => $hours->sum(function (WorkingHour $hour) use ($weekdayCounts) {
                $minutes = CarbonImmutable::parse($hour->start_time)
                    ->diffInMinutes(CarbonImmutable::parse($hour->end_time));

                return $minutes * ($weekdayCounts[$hour->day_of_week] ?? 0);
            }));

        return Employee::query()
            ->where('is_active', true)
            ->get()
            ->map(function (Employee $employee) use ($billable, $workingMinutes) {
                $own = $billable->where('employee_id', $employee->id);

                $bookedMinutes = $own->sum(
                    fn (Appointment $appointment) => $appointment->starts_at->diffInMinutes($appointment->ends_at),
                );

                $available = (float) ($workingMinutes[$employee->id] ?? 0);

                return [
                    'name' => $employee->name,
                    'color' => $employee->color,
                    'count' => $own->count(),
                    'revenue' => (float) $own->sum('price'),
                    'booked_minutes' => (int) $bookedMinutes,
                    'occupancy' => $available > 0
                        ? round(min($bookedMinutes / $available, 1) * 100, 1)
                        : null,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * Cuántas veces aparece cada día de semana dentro del rango.
     *
     * @return array<int, int>
     */
    private function weekdayCounts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = array_fill(0, 7, 0);

        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $counts[$day->dayOfWeek]++;
        }

        return $counts;
    }
}
