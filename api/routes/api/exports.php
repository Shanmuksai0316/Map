<?php

use App\Http\Controllers\Api\V1\ExportController;

Route::prefix('exports')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ExportController::class, 'index']);
    Route::post('/', [ExportController::class, 'store']);
    Route::get('/{exportJob}', [ExportController::class, 'show']);
    Route::get('/{exportJob}/download', [ExportController::class, 'download']);
    Route::post('/{exportJob}/cancel', [ExportController::class, 'cancel']);
});

