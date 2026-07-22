<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles GLOBALES (company_id = null): se definen una sola vez y la
 * asignación a usuarios se scopea por empresa vía model_has_roles.company_id.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Agenda
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',
            // Catálogo del negocio
            'services.manage',
            'employees.manage',
            'resources.manage',
            'branches.manage',
            // Clientes
            'customers.view',
            'customers.manage',
            // Reportes y configuración
            'reports.view',
            'company.settings',
            'widget.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Rol de plataforma: sin permisos asignados — pasa por Gate::before
        Role::findOrCreate('super-admin', 'web');

        $owner = Role::findOrCreate('owner', 'web');
        $owner->syncPermissions($permissions);

        $staff = Role::findOrCreate('staff', 'web');
        $staff->syncPermissions([
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',
            'customers.view',
            'customers.manage',
        ]);

        // Cliente con login: portal de fase 2 (rol modelado, sin permisos aún)
        Role::findOrCreate('client', 'web');
    }
}
