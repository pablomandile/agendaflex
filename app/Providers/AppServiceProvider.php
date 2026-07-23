<?php

namespace App\Providers;

use App\Models\User;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Contexto del tenant activo, compartido por panel, API y jobs
        $this->app->singleton(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // El super-admin de plataforma pasa todos los checks de permisos
        Gate::before(function (User $user): ?bool {
            return $user->is_super_admin ? true : null;
        });

        $this->configureRateLimiting();
    }

    /**
     * Rate limits de la API pública del widget, por clave pública + IP.
     */
    protected function configureRateLimiting(): void
    {
        $byKeyAndIp = fn (Request $request): string => ($request->header('X-Public-Key') ?: 'anon').'|'.$request->ip();

        RateLimiter::for('widget', fn (Request $request) => Limit::perMinute(120)->by($byKeyAndIp($request)));

        // Crear reservas es mucho más sensible al abuso
        RateLimiter::for('widget-book', fn (Request $request) => Limit::perMinute(10)->by($byKeyAndIp($request)));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
