<?php

use App\Http\Controllers\Api\V1\Staff\SupervisorController;

/*
|--------------------------------------------------------------------------
| Supervisor API Routes (HK & RM)
|--------------------------------------------------------------------------
|
| Endpoints for HK Supervisor and RM Supervisor roles.
| Requires authentication and appropriate supervisor role.
|
*/

Route::middleware(['auth:sanctum'])->prefix('supervisor')->group(function () {
    
    // Dashboard stats
    Route::get('/dashboard', [SupervisorController::class, 'dashboard']);
    
    // Assigned tickets
    Route::get('/tickets', [SupervisorController::class, 'tickets']);
    
});

