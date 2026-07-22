<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API pública del widget: identifica el tenant por slug en el path +
 * clave pública en el header X-Public-Key. No autentica usuarios.
 */
class ResolveCompanyFromPublicKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('company');
        $publicKey = $request->header('X-Public-Key');

        if (! is_string($slug) || blank($publicKey)) {
            abort(401, 'Missing tenant credentials.');
        }

        $company = Company::query()
            ->where('slug', $slug)
            ->where('public_key', $publicKey)
            ->first();

        if (! $company || ! $company->isActive()) {
            // 404 y no 403: no filtrar existencia de la empresa
            abort(404);
        }

        $this->assertOriginAllowed($request, $company);

        // Reemplaza el parámetro de ruta por el modelo ya validado
        $request->route()->setParameter('company', $company);

        app(CurrentCompany::class)->set($company);

        return $next($request);
    }

    /**
     * Si la empresa restringió orígenes, el Origin del request debe estar
     * en su lista (el widget corre embebido en dominios de terceros).
     */
    protected function assertOriginAllowed(Request $request, Company $company): void
    {
        $allowed = $company->allowed_origins;

        if (blank($allowed)) {
            return; // sin restricción configurada
        }

        $origin = $request->header('Origin');

        if (blank($origin)) {
            return; // requests server-to-server o del mismo origen
        }

        $host = parse_url($origin, PHP_URL_HOST);

        foreach ($allowed as $allowedOrigin) {
            $allowedHost = parse_url($allowedOrigin, PHP_URL_HOST) ?? $allowedOrigin;

            if (strcasecmp($host ?? '', $allowedHost) === 0) {
                return;
            }
        }

        abort(403, 'Origin not allowed.');
    }
}
