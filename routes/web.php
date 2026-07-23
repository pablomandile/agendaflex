<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\CustomerSearchController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TimeOffController;
use App\Models\Company;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// OAuth con Google: navegación full-page (no visitas Inertia/XHR)
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

// Gestión del turno sin login: links firmados enviados por email
Route::middleware('signed')->group(function () {
    Route::get('booking/{appointment:uuid}', [PublicBookingController::class, 'show'])->name('booking.manage');
    Route::post('booking/{appointment:uuid}/cancel', [PublicBookingController::class, 'cancel'])->name('booking.cancel');
});

// Solo local: abre la demo del widget con los datos de la empresa seed
if (app()->environment('local')) {
    Route::get('widget-demo', function () {
        $company = Company::query()->where('slug', 'estudio-norte')->first();

        abort_unless($company !== null, 404, 'Corré primero: php artisan db:seed --class=DemoSeeder');

        return redirect("/widget-demo.html?tenant={$company->slug}&key={$company->public_key}");
    });
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::post('company/switch', CompanySwitchController::class)->name('company.switch');

    // Agenda: requieren empresa activa + permiso correspondiente
    Route::middleware('company.selected')->group(function () {
        Route::get('calendar', [CalendarController::class, 'index'])
            ->middleware('can:appointments.view')->name('calendar');
        Route::get('calendar/events', [CalendarController::class, 'events'])
            ->middleware('can:appointments.view')->name('calendar.events');
        Route::get('availability', AvailabilityController::class)
            ->middleware('can:appointments.view')->name('availability');
        Route::get('customers/search', CustomerSearchController::class)
            ->middleware('can:customers.view')->name('customers.search');

        Route::post('appointments', [AppointmentController::class, 'store'])
            ->middleware('can:appointments.create')->name('appointments.store');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
            ->middleware('can:appointments.cancel')->name('appointments.cancel');
        Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
            ->middleware('can:appointments.update')->name('appointments.reschedule');

        Route::get('reports', ReportsController::class)
            ->middleware('can:reports.view')->name('reports');

        // Bloqueos de agenda desde el calendario
        Route::post('time-off', [TimeOffController::class, 'store'])
            ->middleware('can:appointments.update')->name('time-off.store');
        Route::delete('time-off/{timeOff}', [TimeOffController::class, 'destroy'])
            ->middleware('can:appointments.update')->name('time-off.destroy');
    });
});

require __DIR__.'/settings.php';
