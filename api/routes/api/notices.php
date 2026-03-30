<?php

use App\Http\Controllers\Api\V1\NoticeController;

// Student/Staff notice viewing routes
Route::prefix('notices')->group(function () {
    Route::get('/', [NoticeController::class, 'index']);
    Route::get('/{notice}', [NoticeController::class, 'show']);
});

