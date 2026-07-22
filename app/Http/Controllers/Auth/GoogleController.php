<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    /**
     * Redirige a la pantalla de consentimiento de Google.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Callback de Google: busca o crea el usuario y lo loguea.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors([
                'email' => __('No pudimos validar el inicio de sesión con Google. Intentá de nuevo.'),
            ]);
        }

        // Matchear primero por google_id; si no, vincular una cuenta existente por email
        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $user->avatar ?? $googleUser->getAvatar(),
                ]);
            } else {
                $user = User::query()->create([
                    'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Usuario',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => null,
                ]);

                // Google ya verificó el email
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
