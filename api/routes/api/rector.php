<?php

use App\Http\Controllers\Api\V1\RectorDashboardController;
use App\Http\Controllers\Api\V1\RectorReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rector Dashboard & Approvals API Routes
|--------------------------------------------------------------------------
|
| Campus-wide aggregation endpoints for Rector role.
| Requires authentication and Rector role.
|
*/

Route::middleware(['auth:sanctum'])->prefix('rector')->group(function () {
    
    // Executive Dashboard - Campus-wide metrics
    Route::get('/dashboard', [RectorDashboardController::class, 'dashboard'])
        ->name('api.rector.dashboard');
    
    // Approval Queue - All pending out-passes
    Route::get('/approvals', [RectorDashboardController::class, 'approvals'])
        ->name('api.rector.approvals');
    
    // Bulk Approval - Approve multiple out-passes at once
    Route::post('/approvals/bulk', [RectorDashboardController::class, 'bulkApprove'])
        ->name('api.rector.approvals.bulk');
    
    // Incidents Timeline
    Route::get('/incidents', [RectorDashboardController::class, 'incidents'])
        ->name('api.rector.incidents');
    
    // Hostel Health & Compliance
    Route::get('/hostels/health', [RectorDashboardController::class, 'hostelHealth'])
        ->name('api.rector.hostels.health');
    
    // Analytics & Reports
    Route::get('/analytics', [RectorDashboardController::class, 'analytics'])
        ->name('api.rector.analytics');

    // Leave Management (combined Leave + Sick Leave)
    Route::get('/leaves', [RectorDashboardController::class, 'leaves'])
        ->name('api.rector.leaves.index');
    Route::get('/leaves/{leave}', [RectorDashboardController::class, 'showLeave'])
        ->name('api.rector.leaves.show');
    Route::post('/leaves/{leave}/approve', [RectorDashboardController::class, 'approveLeave'])
        ->name('api.rector.leaves.approve');
    Route::post('/leaves/{leave}/reject', [RectorDashboardController::class, 'rejectLeave'])
        ->name('api.rector.leaves.reject');
    Route::post('/leaves/bulk-approve', [RectorDashboardController::class, 'bulkApproveLeaves'])
        ->name('api.rector.leaves.bulk-approve');

    // Approval History
    Route::get('/approval-history', [RectorReportController::class, 'getApprovalHistory'])
        ->name('api.rector.approval-history');

    // Monthly Reports
    Route::post('/reports/monthly', [RectorReportController::class, 'generateMonthly'])
        ->name('api.rector.reports.monthly');
});

