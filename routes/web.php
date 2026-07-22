<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CompanySwitchController;
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
});

require __DIR__.'/settings.php';
