<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Token por dispositivo para la app del capitán/chofer o para una integración.
    Route::post('auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:10,1')->name('auth.token');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');

        Route::get('availability', AvailabilityController::class)->name('availability');

        Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('bookings/{booking:code}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking:code}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('bookings/{booking:code}/payments', [PaymentController::class, 'store'])->name('bookings.payments.store');
    });
});
