<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas que operan sobre datos de una empresa: exigen tenant activo
 * (evita queries sin scope, p.ej. un super-admin sin membresías).
 */
class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(CurrentCompany::class)->check(), 403, 'No hay una empresa activa.');

        return $next($request);
    }
}
