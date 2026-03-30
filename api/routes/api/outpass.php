<?php

use App\Http\Controllers\Api\V1\OutPassController;
use App\Http\Controllers\Api\V1\OutPassExportController;

Route::prefix('outpasses')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [OutPassController::class, 'index']);
    Route::post('/', [OutPassController::class, 'store']);
    Route::get('/{outpass}', [OutPassController::class, 'show']);
    Route::put('/{outpass}', [OutPassController::class, 'update']);
    Route::delete('/{outpass}', [OutPassController::class, 'destroy']);
    
    Route::get('/exports', [OutPassExportController::class, 'index']);
    Route::post('/exports', [OutPassExportController::class, 'store']);
    Route::get('/exports/{export}', [OutPassExportController::class, 'show']);
});
