<?php

use App\Http\Controllers\Api\V1\CampusController;
use App\Http\Controllers\Api\V1\HostelController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\RoomAllocationController;
use App\Http\Controllers\Api\V1\Imports\StudentImportController;
use App\Http\Controllers\Api\V1\Imports\RoomAllotmentImportController;
use App\Http\Controllers\Api\V1\NoticeController;
use App\Http\Controllers\ChecklistsController;

Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('campuses')->group(function () {
        Route::get('/', [CampusController::class, 'index']);
        Route::post('/', [CampusController::class, 'store']);
        Route::get('/{campus}', [CampusController::class, 'show']);
        Route::put('/{campus}', [CampusController::class, 'update']);
        Route::delete('/{campus}', [CampusController::class, 'destroy']);
    });
    
    Route::prefix('hostels')->group(function () {
        Route::get('/', [HostelController::class, 'index']);
        Route::post('/', [HostelController::class, 'store']);
        Route::get('/{hostel}', [HostelController::class, 'show']);
        Route::put('/{hostel}', [HostelController::class, 'update']);
        Route::delete('/{hostel}', [HostelController::class, 'destroy']);
    });
    
    Route::prefix('rooms')->group(function () {
        Route::get('/', [RoomController::class, 'index']);
        Route::post('/', [RoomController::class, 'store']);
        Route::get('/{room}', [RoomController::class, 'show']);
        Route::put('/{room}', [RoomController::class, 'update']);
        Route::delete('/{room}', [RoomController::class, 'destroy']);
    });
    
    Route::prefix('allocations')->group(function () {
        Route::get('/', [RoomAllocationController::class, 'index']);
        Route::post('/', [RoomAllocationController::class, 'store']);
        Route::get('/{allocation}', [RoomAllocationController::class, 'show']);
        Route::put('/{allocation}', [RoomAllocationController::class, 'update']);
        Route::delete('/{allocation}', [RoomAllocationController::class, 'destroy']);
    });
    
    Route::prefix('imports')->group(function () {
        Route::post('/students/dry-run', [StudentImportController::class, 'dryRun']);
        Route::post('/students/{job}/commit', [StudentImportController::class, 'commit']);
        Route::post('/room-allotments/dry-run', [RoomAllotmentImportController::class, 'dryRun']);
        Route::post('/room-allotments/{job}/commit', [RoomAllotmentImportController::class, 'commit']);
    });
    
    Route::prefix('notices')->group(function () {
        Route::get('/', [NoticeController::class, 'index']);
        Route::post('/', [NoticeController::class, 'store']);
        Route::get('/{notice}', [NoticeController::class, 'show']);
        Route::put('/{notice}', [NoticeController::class, 'update']);
        Route::post('/{notice}/publish', [NoticeController::class, 'publish']);
        Route::post('/{notice}/schedule', [NoticeController::class, 'schedule']);
        Route::get('/{notice}/attachments', [NoticeController::class, 'attachments']);
        Route::post('/{notice}/attachments', [NoticeController::class, 'attach']);
        Route::delete('/{notice}/attachments', [NoticeController::class, 'detach']);
        Route::delete('/{notice}', [NoticeController::class, 'destroy']);
    });
    
    Route::prefix('checklists')->group(function () {
        Route::get('/', [ChecklistsController::class, 'index']);
        Route::post('/', [ChecklistsController::class, 'store']);
        Route::get('/today', [ChecklistsController::class, 'today']);
        Route::get('/{checklist}', [ChecklistsController::class, 'show']);
        Route::put('/{checklist}', [ChecklistsController::class, 'update']);
        Route::delete('/{checklist}', [ChecklistsController::class, 'destroy']);
        
        // Checklist instance operations
        Route::post('/{instance}/items/{code}', [ChecklistsController::class, 'markItem']);
        Route::post('/{instance}/submit', [ChecklistsController::class, 'submit']);
        Route::post('/{instance}/approve', [ChecklistsController::class, 'approve']);
        Route::post('/{instance}/send-back', [ChecklistsController::class, 'sendBack']);
    });
});
