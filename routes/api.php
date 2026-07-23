<?php

use App\Http\Controllers\Api\WidgetAvailabilityController;
use App\Http\Controllers\Api\WidgetBookingController;
use App\Http\Controllers\Api\WidgetCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública del widget (v1)
|--------------------------------------------------------------------------
| Stateless, sin usuarios: el tenant se identifica por slug en el path +
| clave pública en el header X-Public-Key (middleware tenant.public).
| Solo se exponen uuid/slug, nunca IDs internos.
*/

Route::prefix('v1/{company}')
    ->middleware('tenant.public')
    ->group(function () {
        Route::get('catalog', WidgetCatalogController::class)
            ->middleware('throttle:widget')
            ->name('api.widget.catalog');

        Route::get('availability', WidgetAvailabilityController::class)
            ->middleware('throttle:widget')
            ->name('api.widget.availability');

        Route::post('bookings', WidgetBookingController::class)
            ->middleware('throttle:widget-book')
            ->name('api.widget.bookings');
    });
