<?php

use App\Http\Controllers\Api\V1\AttendanceSessionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampusController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ChecklistsController;
use App\Http\Controllers\Api\V1\GateEntryController;
use App\Http\Controllers\GateController;
use App\Http\Controllers\Api\V1\HostelController;
use App\Http\Controllers\VisitorsController;
use App\Http\Controllers\Api\V1\Imports\RoomAllotmentImportController;
use App\Http\Controllers\Api\V1\Imports\StudentImportController;
use App\Http\Controllers\Api\V1\LaundryCycleController;
use App\Http\Controllers\Api\V1\NoticeController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\OutPassController;
use App\Http\Controllers\Api\V1\OutPassExportController;
use App\Http\Controllers\Api\V1\RoomAllocationController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SportsEquipmentController;
use App\Http\Controllers\Api\V1\SportsEventController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PaymentsController;
use App\Http\Controllers\Api\V1\StudentMeController;
use App\Http\Controllers\Api\V1\LaundryController;
use App\Http\Controllers\Api\V1\SportsController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketCommentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::prefix('onboarding')->group(function (): void {
            Route::post('wizards', [OnboardingController::class, 'store']);
            Route::get('wizards/{wizard}', [OnboardingController::class, 'show']);
            Route::post('wizards/{wizard}/ready-check', [OnboardingController::class, 'readyCheck']);
        });

        Route::prefix('imports')->group(function (): void {
            Route::post('students/dry-run', [StudentImportController::class, 'dryRun']);
            Route::post('students/{job}/commit', [StudentImportController::class, 'commit']);
            Route::post('room-allotments/dry-run', [RoomAllotmentImportController::class, 'dryRun']);
            Route::post('room-allotments/{job}/commit', [RoomAllotmentImportController::class, 'commit']);
        });

        Route::prefix('attendance')->group(function (): void {
            Route::get('sessions', [AttendanceSessionController::class, 'index']);
            Route::post('sessions', [AttendanceSessionController::class, 'store']);
            Route::post('sessions/{session}/students/{student}/mark', [AttendanceSessionController::class, 'mark']);
        });

        Route::prefix('laundry')->group(function (): void {
            Route::get('requests', [LaundryController::class, 'index']);
            Route::post('requests', [LaundryController::class, 'store']);
            Route::post('requests/{laundryRequest}/status', [LaundryController::class, 'updateStatus']);

            Route::get('cycles', [LaundryCycleController::class, 'index']);
            Route::post('cycles', [LaundryCycleController::class, 'store']);
        });

        Route::prefix('sports')->group(function (): void {
            Route::get('events', [SportsEventController::class, 'index']);
            Route::post('events', [SportsEventController::class, 'store']);
            Route::patch('events/{sportsEvent}', [SportsEventController::class, 'update']);
            Route::post('events/{sportsEvent}/enroll', [SportsEventController::class, 'enroll']);
            Route::post('events/{sportsEvent}/enrollments/{enrollment}', [SportsEventController::class, 'updateEnrollment']);

            Route::get('equipment', [SportsEquipmentController::class, 'index']);
            Route::post('equipment', [SportsEquipmentController::class, 'store']);
            Route::post('equipment/{sportsEquipmentLoan}/return', [SportsEquipmentController::class, 'return']);
        });

        Route::prefix('notices')->group(function (): void {
            Route::get('/', [NoticeController::class, 'index']);
            Route::post('/', [NoticeController::class, 'store']);
            Route::patch('{notice}', [NoticeController::class, 'update']);
            Route::post('{notice}/publish', [NoticeController::class, 'publish']);
            Route::delete('{notice}', [NoticeController::class, 'destroy']);
        });

        Route::prefix('attendance')->group(function (): void {
            Route::get('session/today', [AttendanceController::class, 'today']);
            Route::get('sessions/{session}/rooms', [AttendanceController::class, 'rooms']);
            Route::get('sessions/{session}/rooms/{room}', [AttendanceController::class, 'roster']);
            Route::post('sessions/{session}/rooms/{room}/mark', [AttendanceController::class, 'mark']);
            Route::post('sessions/{session}/rooms/{room}/submit', [AttendanceController::class, 'submitRoom']);
            Route::post('sessions/{session}/rooms/{room}/marks/batch', [AttendanceController::class, 'batchMark']);
        });

        Route::prefix('checklists')->group(function (): void {
            Route::get('today', [ChecklistsController::class, 'today']);
            Route::post('{instance}/items/{code}', [ChecklistsController::class, 'markItem']);
            Route::post('{instance}/submit', [ChecklistsController::class, 'submit']);
            Route::post('{instance}/approve', [ChecklistsController::class, 'approve']);
            Route::post('{instance}/send-back', [ChecklistsController::class, 'sendBack']);
        });

        Route::prefix('gate-entries')->group(function (): void {
            Route::get('/', [GateEntryController::class, 'index']);
            Route::post('/', [GateEntryController::class, 'store']);
            Route::post('sync', [GateEntryController::class, 'sync']);
        });

        Route::prefix('gate')->group(function (): void {
            Route::get('outpasses/today', [GateController::class, 'listOutPasses']);
            Route::post('out', [GateController::class, 'out']);
            Route::post('in', [GateController::class, 'in']);
            
            // Device management
            Route::post('devices/register', [GateController::class, 'registerDevice']);
            Route::post('devices/heartbeat', [GateController::class, 'heartbeat']);
            
            // Visitor management
            Route::get('visitors/today', [GateController::class, 'listVisitors']);
            Route::post('visitors/{id}/allow', [GateController::class, 'allowVisitor']);
            Route::post('visitors/{id}/deny', [GateController::class, 'denyVisitor']);
        });

        Route::prefix('visitors')->group(function (): void {
            Route::post('/', [VisitorsController::class, 'store']);
            Route::get('mine/today', [VisitorsController::class, 'mineToday']);
            Route::delete('{guestVisit}', [VisitorsController::class, 'cancel']);
        });

        Route::get('campuses', [CampusController::class, 'index']);
        Route::get('hostels', [HostelController::class, 'index']);
        Route::apiResource('rooms', RoomController::class);
        Route::apiResource('room-allocations', RoomAllocationController::class)->only(['index', 'store', 'update', 'destroy']);

        // P2: Add idempotency middleware to write endpoints
        Route::middleware(['idempotency'])->group(function (): void {
            Route::post('outpasses', [OutPassController::class, 'store']);
            Route::post('attendance/sessions/{session}/rooms/{room}/mark', [AttendanceController::class, 'mark']);
            Route::post('attendance/sessions/{session}/rooms/{room}/submit', [AttendanceController::class, 'submitRoom']);
        });

        Route::apiResource('outpasses', OutPassController::class)->only(['index', 'show', 'update']);
        Route::post('outpasses/{outpass}/cancel', [OutPassController::class, 'cancel'])->name('outpasses.cancel');
        Route::post('outpasses/export', [OutPassExportController::class, 'store']);
        Route::get('outpasses/export/{export}', [OutPassExportController::class, 'show']);

        // Tickets routes
        Route::prefix('tickets')->group(function (): void {
            Route::get('/', [TicketController::class, 'index']);
            Route::post('/', [TicketController::class, 'store']);
            Route::get('/{ticket}', [TicketController::class, 'show']);
            Route::post('/{ticket}/assign', [TicketController::class, 'assign']);
            Route::post('/{ticket}/status', [TicketController::class, 'updateStatus']);
            
            Route::get('/{ticket}/comments', [TicketCommentController::class, 'index']);
            Route::post('/{ticket}/comments', [TicketCommentController::class, 'store']);
            
            // P2: File attachment presign
            Route::post('/{ticket}/attachments/presign', [AttachmentController::class, 'presign']);
        });

        // P2: Push notification device management
        Route::post('devices/register', [DeviceController::class, 'register']);
        
        // P2: File attachment presign
        Route::post('attachments/presign', [AttachmentController::class, 'presign']);
        
        // P3: OTP endpoints
        Route::prefix('otp')->group(function (): void {
            Route::post('start', [\App\Http\Controllers\Api\V1\OtpController::class,'start']);
            Route::post('verify', [\App\Http\Controllers\Api\V1\OtpController::class,'verify']);
        });
        
        // P3: Payments endpoints
        Route::prefix('payments')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\PaymentsController::class,'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\PaymentsController::class,'store']);
        });
        
        // Student profile endpoint
        Route::get('/students/me', [StudentMeController::class,'show']);
        
        // Warden session history + room marking
        Route::prefix('attendance')->group(function (): void {
            Route::get('/sessions/history', [\App\Http\Controllers\AttendanceController::class,'history']);
            Route::get('/sessions/{sid}/rooms/{rid}', [\App\Http\Controllers\AttendanceController::class,'room']);
            Route::post('/sessions/{sid}/rooms/{rid}/mark', [\App\Http\Controllers\AttendanceController::class,'mark']);
            Route::post('/sessions/{sid}/rooms/{rid}/submit', [\App\Http\Controllers\AttendanceController::class,'submit']);
        });
        
        // Laundry manager endpoints
        Route::prefix('laundry')->group(function (): void {
            Route::get('/cycles', [LaundryController::class,'index']);
            Route::get('/cycles/{id}', [LaundryController::class,'show']);
            Route::post('/cycles/{id}/status', [LaundryController::class,'status']);
        });
        
        // Sports manager endpoints
        Route::prefix('sports')->group(function (): void {
            Route::get('/events', [SportsController::class,'index']);
            Route::get('/events/{id}', [SportsController::class,'show']);
            Route::post('/events/{id}/enrollments', [SportsController::class,'enroll']);
            Route::delete('/enrollments/{id}', [SportsController::class,'unenroll']);
        });
    });
});
