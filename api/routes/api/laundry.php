<?php

use App\Http\Controllers\Api\V1\LaundryCycleController;
use App\Http\Controllers\Api\V1\LaundryController;
use App\Http\Controllers\Api\V1\LaundryRequestController;

Route::prefix('laundry')->middleware(['auth:sanctum'])->group(function () {
    // Legacy routes (for backward compatibility)
    Route::get('/', [LaundryController::class, 'index']);
    Route::get('/{id}', [LaundryController::class, 'show']);
    Route::post('/{id}/status', [LaundryController::class, 'status']);
    
    // Laundry Requests
    Route::prefix('requests')->group(function () {
        Route::get('/', [LaundryRequestController::class, 'index']);
        Route::post('/', [LaundryRequestController::class, 'store']);
        Route::get('/{laundryRequest}', [LaundryRequestController::class, 'show']);
        Route::put('/{laundryRequest}', [LaundryRequestController::class, 'update']);
        Route::delete('/{laundryRequest}', [LaundryRequestController::class, 'destroy']);
        
        // Status management
        Route::put('/{laundryRequest}/status', [LaundryRequestController::class, 'updateStatus']);
        Route::post('/{laundryRequest}/collect', [LaundryRequestController::class, 'collect']);
        Route::post('/{laundryRequest}/deliver', [LaundryRequestController::class, 'deliver']);
        Route::post('/{laundryRequest}/manual-verify', [LaundryRequestController::class, 'manualVerify']);
        Route::post('/{laundryRequest}/cancel', [LaundryRequestController::class, 'cancel']);
        Route::post('/{laundryRequest}/mark-lost', [LaundryRequestController::class, 'markAsLost']);
        Route::post('/{laundryRequest}/mark-damaged', [LaundryRequestController::class, 'markAsDamaged']);
    });
    
    // Laundry Cycles
    Route::prefix('cycles')->group(function () {
        Route::get('/', [LaundryCycleController::class, 'index']);
        Route::post('/', [LaundryCycleController::class, 'store']);
        Route::get('/{cycle}', [LaundryCycleController::class, 'show']);
        Route::put('/{cycle}/status', [LaundryCycleController::class, 'updateStatus']);
        Route::delete('/{cycle}', [LaundryCycleController::class, 'destroy']);
    });
    
    // Metrics and analytics
    Route::get('/metrics', [LaundryRequestController::class, 'metrics']);
    
    // Laundry Manager - Raise request on behalf of student
    Route::post('/requests/raise', [LaundryRequestController::class, 'raiseForStudent'])
        ->middleware('role:Laundry Manager');
    
    // Laundry Manager - Mark ready for pickup
    Route::post('/requests/{laundryRequest}/ready-for-pickup', [LaundryRequestController::class, 'markReadyForPickup'])
        ->middleware('role:Laundry Manager');
});
