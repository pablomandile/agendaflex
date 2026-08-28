<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Tests\TestCase;

class InertiaCacheHeadersTest extends TestCase
{
    /** La versión del asset, o Inertia contesta 409 en vez de la página. */
    private function versionDeInertia(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }

    public function test_prohibe_guardar_la_respuesta_xhr_de_inertia(): void
    {
        $respuesta = $this->get('/login', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->versionDeInertia(),
        ]);

        $respuesta->assertOk();
        $this->assertStringContainsString('application/json', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
    }

    public function test_deja_cacheable_el_documento_html_para_no_perder_el_bfcache(): void
    {
        $respuesta = $this->get('/login');

        $this->assertStringContainsString('text/html', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringNotContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
    }
}
