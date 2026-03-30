<?php

use App\Http\Controllers\Api\V1\IncidentController;

Route::prefix('incidents')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [IncidentController::class, 'index']);
    Route::post('/', [IncidentController::class, 'store']);
    Route::get('/{incident}', [IncidentController::class, 'show']);
    Route::put('/{incident}', [IncidentController::class, 'update']);
    Route::post('/{incident}/close', [IncidentController::class, 'close']);
});

