<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cambia la empresa activa de la sesión (usuarios con más de una membresía).
 */
class CompanySwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        // Solo membresías reales: nunca confiar en el id del request
        abort_unless($user->belongsToCompany((int) $validated['company_id']), 403);

        $request->session()->put('current_company_id', (int) $validated['company_id']);

        // Invalida el caché de roles/permisos de la empresa anterior
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return back();
    }
}
