<?php

use App\Http\Controllers\Api\V1\Student\GatePassController;
use App\Http\Controllers\Api\V1\Student\AttendanceController;
use App\Http\Controllers\Api\V1\Student\NoticeController;
use App\Http\Controllers\Api\V1\Student\ProfileController;
use App\Http\Controllers\Api\V1\Student\LeaveController;
use App\Http\Controllers\Api\V1\Student\SickLeaveController;
use App\Http\Controllers\Api\V1\Student\GuestEntryController;
use App\Http\Controllers\Api\V1\Student\RoomChangeController;

/*
|--------------------------------------------------------------------------
| Student API Routes
|--------------------------------------------------------------------------
|
| Student-facing API endpoints for mobile app.
| All routes require authentication and student role.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Profile endpoint (alias to /auth/me for mobile compatibility)
    Route::get('/profile', [ProfileController::class, 'show']);
    
    // Gate Passes (OutPass from student perspective)
    Route::prefix('gate-passes')->group(function () {
        Route::get('/', [GatePassController::class, 'index']);
        Route::post('/', [GatePassController::class, 'store']);
        Route::get('/{id}', [GatePassController::class, 'show']);
    });
    
    // Student Attendance (read-only view)
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::get('/stats', [AttendanceController::class, 'stats']);
    });
    
    // Notices (public student-visible notices)
    Route::prefix('notices')->group(function () {
        Route::get('/', [NoticeController::class, 'index']);
        Route::get('/{id}', [NoticeController::class, 'show']);
    });
    
    // Leaves
    Route::prefix('leaves')->group(function () {
        Route::get('/', [LeaveController::class, 'index']);
        Route::post('/', [LeaveController::class, 'store']);
        Route::get('/{id}', [LeaveController::class, 'show']);
    });
    
    // Sick Leaves
    Route::prefix('sick-leaves')->group(function () {
        Route::get('/', [SickLeaveController::class, 'index']);
        Route::post('/', [SickLeaveController::class, 'store']);
        Route::get('/{id}', [SickLeaveController::class, 'show']);
    });
    
    // Guest Entries
    Route::prefix('guest-entries')->group(function () {
        Route::get('/', [GuestEntryController::class, 'index']);
        Route::post('/', [GuestEntryController::class, 'store']);
        Route::get('/{id}', [GuestEntryController::class, 'show']);
    });
    
    // Room Changes
    Route::prefix('room-changes')->group(function () {
        Route::get('/', [RoomChangeController::class, 'index']);
        Route::post('/', [RoomChangeController::class, 'store']);
        Route::get('/{id}', [RoomChangeController::class, 'show']);
    });
    
});

