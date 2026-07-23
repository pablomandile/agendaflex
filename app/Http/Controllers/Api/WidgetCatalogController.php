<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo público del widget: todo lo que necesita para iniciarse en
 * una sola llamada. Solo expone uuid/slug — nunca IDs internos.
 */
class WidgetCatalogController extends Controller
{
    public function __invoke(Company $company): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (Branch $branch) => [
                'slug' => $branch->slug,
                'name' => $branch->name,
                'address' => $branch->address,
                'timezone' => $branch->effectiveTimezone(),
            ]);

        $services = Service::query()
            ->where('is_active', true)
            ->with(['category:id,name', 'employees:employees.id,employees.uuid'])
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => [
                'uuid' => $service->uuid,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price' => (float) $service->price,
                'max_capacity' => $service->max_capacity,
                'category' => $service->category?->name,
                'employee_uuids' => $service->employees->pluck('uuid')->values(),
            ]);

        $employees = Employee::query()
            ->where('is_active', true)
            ->with('branch:id,slug')
            ->get()
            ->map(fn (Employee $employee) => [
                'uuid' => $employee->uuid,
                'name' => $employee->name,
                'color' => $employee->color,
                'branch_slug' => $employee->branch->slug,
            ]);

        return response()->json([
            'company' => [
                'name' => $company->name,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
                'locale' => $company->locale,
                'branding' => data_get($company->settings, 'branding'),
            ],
            'branches' => $branches,
            'services' => $services,
            'employees' => $employees,
        ]);
    }
}
