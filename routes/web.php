<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\CustomerSearchController;
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
    });
});

require __DIR__.'/settings.php';
