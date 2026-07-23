<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Resource;
use App\Models\Service;
use App\Models\WaitlistEntry;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reservar / cancelar / reprogramar turnos.
 *
 * La reserva corre en una transacción con SELECT ... FOR UPDATE sobre los
 * turnos del empleado y los recursos: dos reservas concurrentes al mismo
 * slot se serializan y la segunda falla con SlotUnavailableException.
 */
class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private AppointmentNotifier $notifier,
    ) {}

    /**
     * @param  Customer|array{name: string, email: string, phone?: string|null}  $customer
     */
    public function book(
        Branch $branch,
        Service $service,
        Employee $employee,
        Customer|array $customer,
        CarbonImmutable $startsAt,
        string $source = 'panel',
        ?string $notes = null,
        bool $notify = true,
    ): Appointment {
        $this->assertSameCompany($branch, $service, $employee);

        $override = $employee->services()->whereKey($service->id)->first()?->pivot;
        $duration = $override?->custom_duration_minutes ?? $service->duration_minutes;
        $price = $override?->custom_price ?? $service->price;

        $startsAt = $startsAt->utc();
        $endsAt = $startsAt->addMinutes((int) $duration);

        $appointment = DB::transaction(function () use ($branch, $service, $employee, $customer, $startsAt, $endsAt, $price, $source, $notes) {
            // 1. Lock del rango: bloquea los turnos activos cercanos del
            //    empleado y serializa cualquier reserva concurrente.
            $existing = Appointment::query()
                ->with('service')
                ->where('employee_id', $employee->id)
                ->whereIn('status', Appointment::ACTIVE_STATUSES)
                ->where('starts_at', '<', $endsAt->addMinutes(AvailabilityService::BUFFER_SEARCH_MARGIN_MINUTES))
                ->where('ends_at', '>', $startsAt->subMinutes(AvailabilityService::BUFFER_SEARCH_MARGIN_MINUTES))
                ->lockForUpdate()
                ->get();

            $groupMates = $this->groupMates($existing, $service, $startsAt);

            // 2. Re-verificar solape sobre datos lockeados
            if ($this->hasConflict($existing, $groupMates, $service, $startsAt, $endsAt)) {
                throw new SlotUnavailableException('El horario ya no está disponible.');
            }

            // 3. El slot debe caer dentro de la agenda del empleado
            //    (para un cupo grupal ya lo garantiza el turno original)
            if ($groupMates->isEmpty() && ! $this->availability->isAvailable($branch, $service, $employee, $startsAt)) {
                throw new SlotUnavailableException('El horario está fuera de la agenda del empleado.');
            }

            // 4. Recursos: un grupo comparte los del turno original
            $resourceIds = $groupMates->isNotEmpty()
                ? $groupMates->first()->resources()->pluck('resources.id')->all()
                : $this->reserveResources($branch, $service, $startsAt, $endsAt);

            $appointment = Appointment::query()->create([
                'branch_id' => $branch->id,
                'customer_id' => $this->resolveCustomer($customer)->id,
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'confirmed',
                'price' => $price,
                'currency' => $branch->company->currency,
                'source' => $source,
                'notes' => $notes,
            ]);

            $appointment->resources()->attach($resourceIds);

            return $appointment;
        });

        // Fuera de la transacción: un rollback no debe encolar emails
        if ($notify) {
            $this->notifier->confirmed($appointment);
        }

        return $appointment;
    }

    public function cancel(Appointment $appointment, ?string $reason = null): Appointment
    {
        if (! $appointment->isActive()) {
            throw new SlotUnavailableException('El turno ya no está activo.');
        }

        $appointment->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'notes' => $reason !== null
                ? trim(($appointment->notes ?? '')."\nCancelación: {$reason}")
                : $appointment->notes,
        ])->save();

        $this->notifier->cancelled($appointment);
        $this->promoteWaitlist($appointment);

        return $appointment;
    }

    /**
     * Reprograma cancelando el original y creando un turno nuevo enlazado
     * vía rescheduled_from_id (el historial no se pierde).
     */
    public function reschedule(Appointment $appointment, CarbonImmutable $newStartsAt, ?Employee $newEmployee = null): Appointment
    {
        if (! $appointment->isActive()) {
            throw new SlotUnavailableException('El turno ya no está activo.');
        }

        $employee = $newEmployee ?? $appointment->employee;

        $new = DB::transaction(function () use ($appointment, $newStartsAt, $employee) {
            // Cancelar primero libera el slot original (permite mover el
            // turno unos minutos con solape sobre sí mismo)
            $appointment->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

            $new = $this->book(
                branch: $appointment->branch,
                service: $appointment->service,
                employee: $employee,
                customer: $appointment->customer,
                startsAt: $newStartsAt,
                source: $appointment->source,
                notes: $appointment->notes,
                notify: false, // se envía "reprogramado", no "confirmado"
            );

            $new->forceFill(['rescheduled_from_id' => $appointment->id])->save();

            return $new;
        });

        $this->notifier->rescheduled($new);
        $this->promoteWaitlist($appointment);

        return $new;
    }

    private function groupMates(Collection $existing, Service $service, CarbonImmutable $startsAt): Collection
    {
        if ($service->max_capacity <= 1) {
            return collect();
        }

        return $existing->filter(
            fn (Appointment $appointment) => $appointment->service_id === $service->id
                && $appointment->starts_at->equalTo($startsAt),
        );
    }

    private function hasConflict(
        Collection $existing,
        Collection $groupMates,
        Service $service,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): bool {
        // Cupo grupal completo
        if ($service->max_capacity > 1 && $groupMates->count() >= $service->max_capacity) {
            return true;
        }

        $blockStart = $startsAt->subMinutes($service->buffer_before_minutes);
        $blockEnd = $endsAt->addMinutes($service->buffer_after_minutes);

        return $existing
            ->reject(fn (Appointment $appointment) => $groupMates->contains('id', $appointment->id))
            ->contains(function (Appointment $appointment) use ($blockStart, $blockEnd) {
                $start = $appointment->starts_at->subMinutes($appointment->service->buffer_before_minutes);
                $end = $appointment->ends_at->addMinutes($appointment->service->buffer_after_minutes);

                return $start->lt($blockEnd) && $end->gt($blockStart);
            });
    }

    /**
     * Elige y lockea los recursos requeridos libres en la franja.
     *
     * @return array<int, int>
     */
    private function reserveResources(Branch $branch, Service $service, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $ids = [];

        foreach ($service->requiredResourceTypes as $type) {
            $quantity = (int) $type->pivot->quantity;

            $candidates = Resource::query()
                ->where('branch_id', $branch->id)
                ->where('resource_type_id', $type->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            $busyIds = DB::table('appointment_resource')
                ->join('appointments', 'appointments.id', '=', 'appointment_resource.appointment_id')
                ->whereIn('appointment_resource.resource_id', $candidates->modelKeys())
                ->whereIn('appointments.status', Appointment::ACTIVE_STATUSES)
                ->where('appointments.starts_at', '<', $endsAt)
                ->where('appointments.ends_at', '>', $startsAt)
                ->pluck('appointment_resource.resource_id');

            $free = $candidates
                ->reject(fn (Resource $resource) => $busyIds->contains($resource->id))
                ->take($quantity);

            if ($free->count() < $quantity) {
                throw new SlotUnavailableException("No hay \"{$type->name}\" disponible para ese horario.");
            }

            $ids = array_merge($ids, $free->modelKeys());
        }

        return $ids;
    }

    /**
     * @param  Customer|array{name: string, email: string, phone?: string|null}  $customer
     */
    private function resolveCustomer(Customer|array $customer): Customer
    {
        if ($customer instanceof Customer) {
            return $customer;
        }

        // Matcheo por email dentro de la empresa (scope global)
        return Customer::query()->firstOrCreate(
            ['email' => $customer['email']],
            ['name' => $customer['name'], 'phone' => $customer['phone'] ?? null],
        );
    }

    /**
     * Un slot se liberó: marca y avisa por email al primero de la lista
     * de espera que matchee servicio/sucursal/empleado/rango.
     */
    private function promoteWaitlist(Appointment $appointment): void
    {
        $entry = WaitlistEntry::query()
            ->where('status', 'waiting')
            ->where('service_id', $appointment->service_id)
            ->where('branch_id', $appointment->branch_id)
            ->where(fn ($query) => $query->whereNull('employee_id')->orWhere('employee_id', $appointment->employee_id))
            ->where('desired_from', '<', $appointment->ends_at)
            ->where('desired_to', '>', $appointment->starts_at)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();

        if ($entry) {
            $entry->update(['status' => 'notified']);
            $this->notifier->waitlistSlotFreed($entry);
        }
    }

    private function assertSameCompany(Branch $branch, Service $service, Employee $employee): void
    {
        $companyId = app(CurrentCompany::class)->id() ?? $branch->company_id;

        foreach ([$branch, $service, $employee] as $model) {
            if ($model->company_id !== $companyId) {
                throw new InvalidArgumentException('Las entidades pertenecen a empresas distintas.');
            }
        }

        if ($employee->branch_id !== $branch->id) {
            throw new InvalidArgumentException('El empleado no pertenece a la sucursal.');
        }
    }
}
