<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AttachmentsController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\StudentMeController;

Route::prefix('auth')->group(function () {
    // Rate limit login attempts: 5 attempts per minute per IP
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    
    // OTP send: No throttle for testing (bypass OTP 123456 available)
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    
    // Rate limit OTP verify: 5 attempts per 15 minutes per phone number
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:5,15');
    
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::prefix('devices')->group(function () {
    Route::post('/register', [DeviceController::class, 'register']);
});

Route::prefix('attachments')->middleware('auth:sanctum')->group(function () {
    Route::post('/presign', [AttachmentController::class, 'presign']);
    Route::post('/', [AttachmentsController::class, 'upload']);
});

Route::prefix('otp')->group(function () {
    Route::post('/start', [OtpController::class, 'start']);
    Route::post('/verify', [OtpController::class, 'verify']);
});

Route::prefix('student')->group(function () {
    Route::get('/me', [StudentMeController::class, 'show']);
});
