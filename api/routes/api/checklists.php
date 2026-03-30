<?php

use App\Http\Controllers\ChecklistsController;
use Illuminate\Support\Facades\Route;

Route::prefix('checklists')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/today', [ChecklistsController::class, 'today']);

    Route::post('/{instance}/items/{code}', [ChecklistsController::class, 'markItem']);
    Route::post('/{instance}/submit', [ChecklistsController::class, 'submit']);
    Route::post('/{instance}/approve', [ChecklistsController::class, 'approve']);
    Route::post('/{instance}/send-back', [ChecklistsController::class, 'sendBack']);
    Route::post('/{instance}/items/{code}/photo', [ChecklistsController::class, 'uploadPhoto']);
});
