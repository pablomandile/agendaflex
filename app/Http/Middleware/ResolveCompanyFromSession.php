<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel autenticado: resuelve la empresa activa desde la sesión,
 * validada contra las membresías del usuario (company_user).
 */
class ResolveCompanyFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $companyId = $request->session()->get('current_company_id');

        // La empresa de la sesión debe ser una membresía real del usuario
        $company = null;

        if ($companyId !== null) {
            $company = $user->companies()->whereKey($companyId)->first();
        }

        // Sin selección válida: primera membresía como default
        if (! $company) {
            $company = $user->companies()->first();

            if ($company) {
                $request->session()->put('current_company_id', $company->id);
            }
        }

        if ($company) {
            app(CurrentCompany::class)->set($company);

            // Scopea los roles/permisos de spatie a la empresa activa
            setPermissionsTeamId($company->id);
        }

        return $next($request);
    }
}
