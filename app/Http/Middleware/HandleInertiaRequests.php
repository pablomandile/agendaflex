<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        // Inertia lo pone y el CDN de Hostinger lo borra al comprimir con
        // brotli, pero se declara igual: es lo correcto y sirve en cualquier
        // intermediario que sí lo respete.
        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        /*
         * `no-store`, no `no-cache`: `no-cache` permite guardar y solo obliga a
         * revalidar, y una navegación de historial (restaurar una pestaña
         * descartada, el botón "atrás") saltea la revalidación. Sin esto el
         * navegador reusa el JSON guardado y lo muestra crudo en pantalla.
         *
         * Y solo sobre la respuesta XHR, **nunca** sobre el HTML: `no-store` en
         * el documento principal desactiva el back/forward cache de Chrome y
         * convierte cada "atrás" en una ida completa a la red.
         */
        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // Empresas del usuario + activa, para el switcher del sidebar
                'companies' => $user
                    ? $user->companies()
                        ->get(['companies.id', 'companies.name'])
                        ->map(fn ($company) => ['id' => $company->id, 'name' => $company->name])
                    : [],
                'currentCompanyId' => $request->session()->get('current_company_id'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
