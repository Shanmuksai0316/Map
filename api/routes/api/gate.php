<?php

use App\Http\Controllers\Api\V1\GateEntryController;
use App\Http\Controllers\Api\V1\Staff\GuardController;
use App\Http\Controllers\GateController;
use App\Models\GateDutyHandover;

Route::prefix('gate')->group(function () {
    // Gate entries (new API)
    Route::get('/entries', [GateEntryController::class, 'index']);
    Route::post('/entries', [GateEntryController::class, 'store']);
    Route::get('/entries/{entry}', [GateEntryController::class, 'show']);
    Route::put('/entries/{entry}', [GateEntryController::class, 'update']);
    Route::delete('/entries/{entry}', [GateEntryController::class, 'destroy']);
    
    // Gate in/out (legacy API for tests)
    Route::post('/in', [GateController::class, 'in']);
    Route::post('/out', [GateController::class, 'out']);
    
    Route::get('/outpasses/today', [GateController::class, 'listOutPasses']);
    Route::get('/visitors/today', [GateController::class, 'listVisitors']);
    Route::post('/visitors/{id}/allow', [GateController::class, 'allowVisitor']);
    Route::post('/visitors/{id}/deny', [GateController::class, 'denyVisitor']);

    // Duty handover management
    Route::get('/duty-handovers/today', [GateController::class, 'listDutyHandovers']);
    Route::post('/duty-handovers', [GateController::class, 'createDutyHandover']);
    Route::post('/duty-handovers/{handover}/complete', [GateController::class, 'completeDutyHandover']);

    // QR scanning
    Route::post('/scan', [GateController::class, 'scanQR']);
    // OutPass gate-pass scan (QR/backup code)
    Route::post('/outpass-scans', [GateController::class, 'outpassScans']);
    // Verify by 4-digit backup code only (finds outpass, records scan, returns out_pass_id)
    Route::post('/outpass-verify-by-code', [GateController::class, 'outpassVerifyByCode']);

    // OTP and backup code verification
    Route::post('/send-otp', [GateController::class, 'sendOtp']);
    Route::post('/verify-backup-code', [GateController::class, 'verifyBackupCode']);

    // Guard-specific operations (Phase 1.2-1.3)
    Route::post('/emergency-exit', [GuardController::class, 'emergencyExit']);
    Route::get('/passes/active', [GuardController::class, 'getActivePasses']);
    Route::get('/entries/recent', [GuardController::class, 'getRecentEntries']);
});
