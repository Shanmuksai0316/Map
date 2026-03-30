<?php

use App\Http\Controllers\Api\V1\NotificationController;

Route::prefix('notifications')->middleware(['auth:sanctum'])->group(function () {
    // Unread count for bell badge
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    
    // List notifications (paginated)
    Route::get('/', [NotificationController::class, 'index']);
    
    // Mark as read
    Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    
    // Comm Box specific
    Route::get('/comm-box', [NotificationController::class, 'commBox']);
    Route::get('/comm-box/unread', [NotificationController::class, 'commBoxUnread']);
});

