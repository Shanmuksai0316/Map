<?php

use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;

Route::prefix('tickets')->middleware(['auth:sanctum'])->group(function () {
    // Students can list their own tickets and create new ones
    Route::get('/', [TicketController::class, 'index']);
    Route::post('/', [TicketController::class, 'store']);
    Route::get('/{ticket}', [TicketController::class, 'show']);
    
    // Staff/admin only for updates and deletions
    Route::put('/{ticket}', [TicketController::class, 'update']);
    Route::delete('/{ticket}', [TicketController::class, 'destroy']);
    
    // Ticket status transitions (staff/admin)
    Route::post('/{ticket}/status', [TicketController::class, 'updateStatus']);
    
    // Ticket comments (all authenticated users)
    Route::get('/{ticket}/comments', [TicketCommentController::class, 'index']);
    Route::post('/{ticket}/comments', [TicketCommentController::class, 'store']);
});
