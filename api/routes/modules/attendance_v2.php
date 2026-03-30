<?php

use App\Http\Controllers\AttendanceV2Controller;
use App\Support\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('attendance')->middleware(['auth:sanctum'])->group(function () {
    // V2 routes - only available when feature flag is enabled
    if (Feature::isEnabled('attendance_v2')) {
        Route::get('session/today', [AttendanceV2Controller::class, 'today']);
        Route::get('sessions/history', [AttendanceV2Controller::class, 'history']);
        Route::get('sessions/{session}', [AttendanceV2Controller::class, 'show']);
        Route::get('sessions/{session}/rooms', [AttendanceV2Controller::class, 'rooms']);
        Route::get('sessions/{session}/rooms/{room}/roster', [AttendanceV2Controller::class, 'roster']);
        Route::post('sessions/{session}/rooms/{room}/mark', [AttendanceV2Controller::class, 'mark']);
        Route::post('sessions/{session}/rooms/{room}/submit', [AttendanceV2Controller::class, 'submit']);
        Route::get('sessions/{session}/rooms/{room}/students/{student}/reveal', [AttendanceV2Controller::class, 'revealUid']);
    }
    
    // Legacy routes - always registered, but conditionally return 410 when softkill is enabled
    Route::get('today', function () {
        if (Feature::isEnabled('attendance_legacy_softkill')) {
            return response()->json([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ], 410);
        }
        // Fallback to legacy controller if softkill is disabled
        return app(\App\Http\Controllers\AttendanceController::class)->today(request());
    });
    
    Route::get('history', function () {
        if (Feature::isEnabled('attendance_legacy_softkill')) {
            return response()->json([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ], 410);
        }
        return app(\App\Http\Controllers\AttendanceController::class)->history(request());
    });
    
    Route::get('room/{sessionId}/{roomId}', function ($sessionId, $roomId) {
        if (Feature::isEnabled('attendance_legacy_softkill')) {
            return response()->json([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ], 410);
        }
        return app(\App\Http\Controllers\AttendanceController::class)->room($sessionId, $roomId);
    });
    
    Route::post('mark', function (Request $request) {
        // Legacy endpoint - always return deprecated
        return response()->json([
            'message' => 'This endpoint is deprecated. Use /api/v1/attendance/sessions/{session}/rooms/{room}/mark instead.',
            'location' => '/api/v1/attendance/sessions/{session}/rooms/{room}/mark'
        ], 410);
    });
    
    Route::post('submit', function () {
        // Legacy endpoint - always return deprecated
        return response()->json([
            'message' => 'This endpoint is deprecated. Use /api/v1/attendance/sessions/{session}/rooms/{room}/submit instead.',
            'location' => '/api/v1/attendance/sessions/{session}/rooms/{room}/submit'
        ], 410);
    });
});
