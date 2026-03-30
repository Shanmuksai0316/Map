<?php

use App\Http\Controllers\Api\V1\OfflineController;
use Illuminate\Support\Facades\Route;

/**
 * Offline Queue Routes
 * 
 * These routes handle synchronization of offline actions from mobile apps.
 * Used by Guard (gate operations) and Warden (attendance marking).
 */

Route::middleware(['auth:sanctum'])->prefix('offline')->group(function () {
    // Sync offline actions (batch upload)
    Route::post('/sync', [OfflineController::class, 'sync']);
    
    // Get offline action history for current user
    Route::get('/history', [OfflineController::class, 'history']);
    
    // Health check
    Route::get('/health', [OfflineController::class, 'health']);
});

