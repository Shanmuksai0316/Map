<?php

use App\Http\Controllers\Api\V1\Staff\WardenController;
use App\Http\Controllers\Api\V1\CampusManager\EmergencyController;

/*
|--------------------------------------------------------------------------
| Warden API Routes
|--------------------------------------------------------------------------
|
| Warden-specific endpoints for hostel management.
| Requires authentication and Warden role.
|
*/

Route::middleware(['auth:sanctum', 'role:Warden'])->prefix('warden')->group(function () {
    
    // Room management
    Route::get('/rooms', [WardenController::class, 'rooms']);
    Route::get('/rooms/{roomId}/students', [WardenController::class, 'roomStudents']);

    // Student management
    Route::get('/students', [WardenController::class, 'students']);
    Route::get('/students/{student}', [WardenController::class, 'studentDetail']);
    
    // Request/Ticket management
    Route::get('/requests', [WardenController::class, 'requests']);
    
    // Daily checklist
    Route::get('/checklist', [WardenController::class, 'checklist']);
    
    // Unmarked students tracking (Phase 1.5)
    Route::get('/unmarked', [WardenController::class, 'unmarkedStudents']);

    // Attendance submission
    Route::post('/rooms/{roomId}/attendance', [WardenController::class, 'submitAttendance']);

    // Emergency / Incidents (same data as Campus Manager – student-reported emergencies)
    Route::prefix('emergency')->group(function () {
        Route::get('/medical', [EmergencyController::class, 'medicalList']);
        Route::get('/medical/{id}', [EmergencyController::class, 'medicalShow']);
        Route::post('/medical/{id}/acknowledge', [EmergencyController::class, 'acknowledgeMedical']);
        Route::get('/incidents', [EmergencyController::class, 'incidentList']);
        Route::get('/incidents/unread-count', [EmergencyController::class, 'unreadCount']);
        Route::post('/incidents/{incident}/acknowledge', [EmergencyController::class, 'acknowledgeIncident']);
    });
});

