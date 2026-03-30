<?php

use App\Http\Controllers\Api\V1\CampusManager\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('campus-manager/checkouts')->group(function (): void {
    Route::get('upcoming', [CheckoutController::class, 'upcoming']);
    Route::post('{roomAllocation}/start', [CheckoutController::class, 'start']);
    Route::post('{roomAllocation}/complete', [CheckoutController::class, 'complete']);
    Route::post('{roomAllocation}/extend', [CheckoutController::class, 'extend']);
});
