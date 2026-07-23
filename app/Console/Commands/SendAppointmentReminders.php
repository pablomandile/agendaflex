<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Company;
use App\Services\AppointmentNotifier;
use App\Tenancy\CurrentCompany;
use Illuminate\Console\Command;

/**
 * Recordatorios de turnos próximos. Corre por scheduler y, al no haber
 * request, itera las empresas seteando el tenant explícitamente en cada
 * vuelta (los jobs/commands no tienen sesión).
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envía recordatorios por email de los turnos próximos (por empresa)';

    public function handle(CurrentCompany $current, AppointmentNotifier $notifier): int
    {
        $sent = 0;

        Company::query()
            ->where('status', 'active')
            ->each(function (Company $company) use ($current, $notifier, &$sent) {
                $current->set($company);
                setPermissionsTeamId($company->id);

                // Ventana configurable por empresa (default: 24 horas antes)
                $hours = (int) data_get($company->settings, 'reminder_hours', 24);

                Appointment::query()
                    ->with(['customer', 'service', 'employee', 'branch', 'company'])
                    ->whereIn('status', Appointment::ACTIVE_STATUSES)
                    ->whereBetween('starts_at', [now(), now()->addHours($hours)])
                    ->get()
                    ->reject(fn (Appointment $appointment) => $notifier->wasReminded($appointment))
                    ->each(function (Appointment $appointment) use ($notifier, &$sent) {
                        $notifier->reminder($appointment);
                        $sent++;
                    });
            });

        $current->set(null);

        $this->info("Recordatorios encolados: {$sent}");

        return self::SUCCESS;
    }
}
