<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Empresa demo completa para desarrollo: peluquería con dos sucursales,
 * empleados con horarios y skills, recursos, clientes y turnos de ejemplo.
 *
 * Login demo: owner@agendaflex.test / password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // ── Empresa y sucursales ─────────────────────────────────────────
        $company = Company::query()->firstOrCreate(
            ['slug' => 'estudio-norte'],
            [
                'name' => 'Estudio Norte',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'locale' => 'es',
                'currency' => 'ARS',
                'status' => 'active',
            ],
        );

        // Todo lo que sigue se crea scopeado a la empresa demo
        app(CurrentCompany::class)->set($company);
        setPermissionsTeamId($company->id);

        $centro = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'casa-central'],
            ['name' => 'Casa Central', 'address' => 'Av. Cabildo 1234, CABA', 'phone' => '+54 11 4781-0000'],
        );

        $palermo = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'palermo'],
            ['name' => 'Sucursal Palermo', 'address' => 'Gorriti 4800, CABA', 'phone' => '+54 11 4831-0000'],
        );

        // ── Usuarios: super-admin de plataforma y owner del negocio ─────
        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'admin@agendaflex.test'],
            ['name' => 'Platform Admin', 'password' => Hash::make('password'), 'is_super_admin' => true],
        );

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@agendaflex.test'],
            ['name' => 'Dueño Demo', 'password' => Hash::make('password')],
        );

        if (! $owner->belongsToCompany($company)) {
            $owner->companies()->attach($company);
        }

        if (! $owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }

        // ── Catálogo: categorías y servicios ────────────────────────────
        $corte = ServiceCategory::query()->firstOrCreate(['company_id' => $company->id, 'name' => 'Cortes'], ['position' => 1]);
        $color = ServiceCategory::query()->firstOrCreate(['company_id' => $company->id, 'name' => 'Coloración'], ['position' => 2]);
        $barba = ServiceCategory::query()->firstOrCreate(['company_id' => $company->id, 'name' => 'Barbería'], ['position' => 3]);

        $services = collect([
            ['category_id' => $corte->id, 'name' => 'Corte de pelo', 'duration_minutes' => 45, 'price' => 12000],
            ['category_id' => $corte->id, 'name' => 'Corte + lavado', 'duration_minutes' => 60, 'price' => 15000],
            ['category_id' => $color->id, 'name' => 'Coloración completa', 'duration_minutes' => 120, 'buffer_after_minutes' => 15, 'price' => 45000],
            ['category_id' => $color->id, 'name' => 'Reflejos', 'duration_minutes' => 90, 'buffer_after_minutes' => 15, 'price' => 38000],
            ['category_id' => $barba->id, 'name' => 'Arreglo de barba', 'duration_minutes' => 30, 'price' => 8000],
        ])->map(fn (array $attributes) => Service::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => $attributes['name']],
            $attributes,
        ));

        // ── Recursos: sillones por sucursal ──────────────────────────────
        $sillon = ResourceType::query()->firstOrCreate(['company_id' => $company->id, 'name' => 'Sillón']);

        foreach ([$centro->id => 3, $palermo->id => 2] as $branchId => $count) {
            for ($i = 1; $i <= $count; $i++) {
                Resource::query()->firstOrCreate([
                    'company_id' => $company->id,
                    'branch_id' => $branchId,
                    'resource_type_id' => $sillon->id,
                    'name' => "Sillón {$i}",
                ]);
            }
        }

        // Todo servicio de la demo requiere 1 sillón
        $services->each(function (Service $service) use ($sillon) {
            $service->requiredResourceTypes()->syncWithoutDetaching([
                $sillon->id => ['quantity' => 1],
            ]);
        });

        // ── Empleados con skills y horarios ──────────────────────────────
        $employees = collect([
            ['branch_id' => $centro->id, 'name' => 'Carla Gómez', 'color' => '#6366f1', 'skills' => [0, 1, 2, 3]],
            ['branch_id' => $centro->id, 'name' => 'Martín Paz', 'color' => '#10b981', 'skills' => [0, 1, 4]],
            ['branch_id' => $palermo->id, 'name' => 'Lucía Fernández', 'color' => '#f59e0b', 'skills' => [0, 1, 2, 3, 4]],
        ])->map(function (array $attributes) use ($company, $services) {
            $employee = Employee::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $attributes['name']],
                ['branch_id' => $attributes['branch_id'], 'color' => $attributes['color']],
            );

            $employee->services()->syncWithoutDetaching(
                collect($attributes['skills'])->map(fn (int $i) => $services[$i]->id)->all(),
            );

            // Martes a sábado, 9 a 18 con corte al mediodía
            if ($employee->workingHours()->count() === 0) {
                foreach (range(2, 6) as $day) {
                    $employee->workingHours()->createMany([
                        ['company_id' => $company->id, 'day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '13:00'],
                        ['company_id' => $company->id, 'day_of_week' => $day, 'start_time' => '14:00', 'end_time' => '18:00'],
                    ]);
                }
            }

            return $employee;
        });

        // ── Clientes y turnos de ejemplo ─────────────────────────────────
        if (Customer::query()->count() === 0) {
            $customers = Customer::factory()->count(12)->create([
                'company_id' => $company->id,
            ]);

            $tz = $company->timezone;

            foreach (range(0, 14) as $i) {
                $employee = $employees[$i % $employees->count()];
                $service = $services[$i % $services->count()];

                // Días hábiles próximos, en horario laboral local
                $start = now($tz)
                    ->addDays(($i % 5) + 1)
                    ->setTime(9 + ($i % 4) * 2, 0)
                    ->utc();

                Appointment::factory()->create([
                    'company_id' => $company->id,
                    'branch_id' => $employee->branch_id,
                    'customer_id' => $customers[$i % $customers->count()]->id,
                    'service_id' => $service->id,
                    'employee_id' => $employee->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($service->duration_minutes),
                    'price' => $service->price,
                    'currency' => $company->currency,
                ]);
            }
        }
    }
}
