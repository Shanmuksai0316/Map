<?php

use App\Http\Controllers\Api\V1\VisitorController;

Route::prefix('visitors')->middleware('auth:sanctum')->group(function () {
    // Pre-registrations
    Route::prefix('pre-registrations')->group(function () {
        Route::get('/', [VisitorController::class, 'indexPreRegistrations']);
        Route::post('/', [VisitorController::class, 'storePreRegistration']);
        Route::post('/{preRegistration}/approve', [VisitorController::class, 'approvePreRegistration']);
        Route::post('/{preRegistration}/decline', [VisitorController::class, 'declinePreRegistration']);
        Route::post('/{preRegistration}/cancel', [VisitorController::class, 'cancelPreRegistration']);
    });
    
    // Visitor logs (Guard records)
    Route::prefix('logs')->group(function () {
        Route::get('/', [VisitorController::class, 'indexLogs']);
        Route::post('/', [VisitorController::class, 'storeLog']);
    });
});

