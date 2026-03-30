<?php

use App\Http\Controllers\Api\V1\CampusManager\StaffController;
use App\Http\Controllers\Api\V1\CampusManager\EmergencyController;
use App\Http\Controllers\Api\V1\CampusManager\ChecklistExportController;
use App\Http\Controllers\Api\V1\CampusManager\MyChecklistController;
use App\Http\Controllers\Api\V1\IncidentController;

Route::prefix('campus-manager')->middleware(['auth:sanctum', 'role:Campus Manager'])->group(function () {
    // Staff Management
    Route::get('/staff', [StaffController::class, 'index']);
    Route::get('/staff/{user}', [StaffController::class, 'show']);
    Route::get('/staff/hostel/{hostel}', [StaffController::class, 'byHostel']);

    // Dashboard Statistics
    Route::get('/dashboard/stats', [StaffController::class, 'dashboardStats']);

    // Emergency Management
    Route::prefix('emergency')->group(function () {
        // Medical emergencies
        Route::get('/medical', [EmergencyController::class, 'medicalList']);
        Route::get('/medical/{id}', [EmergencyController::class, 'medicalShow']);
        Route::post('/medical/{id}/acknowledge', [EmergencyController::class, 'acknowledgeMedical']);

        // Security incidents
        Route::get('/incidents', [EmergencyController::class, 'incidentList']);
        Route::get('/incidents/unread-count', [EmergencyController::class, 'unreadCount']);
        Route::post('/incidents/{incident}/acknowledge', [EmergencyController::class, 'acknowledgeIncident']);
    });

    // Requests Hub (view-only aggregation)
    Route::prefix('requests')->group(function () {
        Route::get('/housekeeping', [StaffController::class, 'housekeepingRequests']);
        Route::get('/maintenance', [StaffController::class, 'maintenanceRequests']);
        Route::get('/outpass', [StaffController::class, 'outpassRequests']);
        Route::get('/leave', [StaffController::class, 'leaveRequests']);
        Route::get('/guest-entry', [StaffController::class, 'guestEntryRequests']);
        Route::get('/sports', [StaffController::class, 'sportsRequests']);
        Route::get('/laundry', [StaffController::class, 'laundryRequests']);
    });

    // Checklists overview and reporting
    Route::prefix('checklists')->group(function () {
        // My Checklist (assigned to current user) – for mobile "My Checklist" tab
        Route::get('/current', [MyChecklistController::class, 'current']);
        Route::post('/submit', [MyChecklistController::class, 'submit']);
        Route::post('/items/{taskIndex}/complete', [MyChecklistController::class, 'completeTask']);
        Route::post('/items/{taskIndex}/photo', [MyChecklistController::class, 'uploadPhoto']);

        // Summary endpoints
        Route::get('/staff-summary', [StaffController::class, 'staffChecklistSummary']);
        Route::get('/staff/{user}', [StaffController::class, 'staffChecklistDetail']);
        Route::get('/compliance-report', [StaffController::class, 'checklistComplianceReport']);

        // Export and analytics endpoints
        Route::get('/export/csv', [ChecklistExportController::class, 'exportCsv']);
        Route::get('/analytics/daily-summary', [ChecklistExportController::class, 'dailySummary']);
        Route::get('/analytics/role-summary', [ChecklistExportController::class, 'roleSummary']);
        Route::get('/analytics/staff-performance', [ChecklistExportController::class, 'staffPerformance']);
    });
});
