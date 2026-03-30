<?php

use App\Http\Controllers\Api\V1\CampusManager\AutoAllocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('campus-manager/auto-allocation')->group(function (): void {
    Route::get('templates/{mode}', [AutoAllocationController::class, 'downloadTemplate']);
    Route::post('preview', [AutoAllocationController::class, 'preview']);
    Route::post('commit', [AutoAllocationController::class, 'commit']);
});
