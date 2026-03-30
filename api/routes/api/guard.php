<?php

use App\Http\Controllers\Api\V1\Guard\ChecklistController;
use App\Http\Controllers\Api\V1\Staff\GuardController;

Route::prefix('guard')->middleware(['auth:sanctum', 'role:Guard'])->group(function () {
    // Guard Checklist
    Route::get('/checklist', [ChecklistController::class, 'index']);
    Route::get('/checklist/current', [ChecklistController::class, 'current']);
    Route::post('/checklist/{task}/complete', [ChecklistController::class, 'completeTask']);
    Route::post('/checklist/{task}/photo', [ChecklistController::class, 'uploadPhoto']);
    Route::post('/checklist/submit', [ChecklistController::class, 'submit']);
    Route::get('/checklist/history', [ChecklistController::class, 'history']);
    
    // Time Verification
    Route::post('/verify-time', [ChecklistController::class, 'verifyTime']);
    // Compatibility alias (older apps call /guard/gate/verify-time)
    Route::post('/gate/verify-time', [ChecklistController::class, 'verifyTime']);

    // Active requests for Guard gate workflow
    Route::get('/outpasses/active', [GuardController::class, 'activeOutpasses']);
    Route::get('/outpasses/{id}', [GuardController::class, 'showOutpass']);
    Route::get('/leaves/active', [GuardController::class, 'activeLeaves']);
    Route::get('/guest-entries/active', [GuardController::class, 'activeGuestEntries']);
    Route::get('/guest-entries/completed', [GuardController::class, 'completedGuestEntries']);
    Route::post('/guest-entries/{id}/mark-entry', [GuardController::class, 'markGuestEntry']);
    
    // Dashboard Stats
    Route::get('/dashboard/stats', [GuardController::class, 'dashboardStats']);
    
    // History for profile
    Route::get('/history', [GuardController::class, 'history']);
    Route::get('/history/leave', [GuardController::class, 'leaveHistory']);
    Route::get('/history/outpass', [GuardController::class, 'outpassHistory']);
    Route::get('/history/guest-entry', [GuardController::class, 'guestEntryHistory']);
});

// Gate operations (already partially exists in gate.php, extend here)
Route::prefix('gate')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/verify-time', [ChecklistController::class, 'verifyGateTime']);
});

