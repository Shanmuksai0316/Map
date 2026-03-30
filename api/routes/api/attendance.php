<?php

use App\Http\Controllers\Api\V1\AttendanceSessionController;
use App\Http\Controllers\AttendanceController;

Route::prefix('attendance')->group(function () {
    Route::get('/sessions', [AttendanceSessionController::class, 'index']);
    Route::post('/sessions', [AttendanceSessionController::class, 'store']);
    Route::get('/sessions/{session}', [AttendanceSessionController::class, 'show']);
    Route::put('/sessions/{session}', [AttendanceSessionController::class, 'update']);
    Route::delete('/sessions/{session}', [AttendanceSessionController::class, 'destroy']);
    
    // Attendance marking routes
    Route::post('/sessions/{session}/students/{studentId}/mark', [AttendanceSessionController::class, 'mark']);
    Route::put('/sessions/{session}/students/{studentId}/mark', [AttendanceSessionController::class, 'editMark']);
    
    Route::get('/today', [AttendanceController::class, 'today']);
    Route::get('/history', [AttendanceController::class, 'history']);
    Route::get('/room/{sessionId}/{roomId}', [AttendanceController::class, 'room']);
    Route::post('/mark', [AttendanceController::class, 'mark']);
    Route::post('/submit', [AttendanceController::class, 'submit']);
});
