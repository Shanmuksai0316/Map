<?php

use App\Http\Controllers\Api\V1\CampusManager\RoomChangeApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('campus-manager')->group(function (): void {
    Route::get('room-changes', [RoomChangeApprovalController::class, 'index']);
    Route::get('room-changes/{roomChange}', [RoomChangeApprovalController::class, 'show']);
    Route::post('room-changes/{roomChange}/approve', [RoomChangeApprovalController::class, 'approve']);
    Route::post('room-changes/{roomChange}/reject', [RoomChangeApprovalController::class, 'reject']);
});
