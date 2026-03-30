<?php

namespace App\Listeners;

use App\Events\StudentActivated;
use App\Services\AuditLogger;
use App\Services\Notifications\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendStudentWelcomeNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     * 
     * Sends welcome SMS with OTP for first login when student is activated.
     * Implements PRD §4.2: "Activate N → Welcome SMS with OTP login"
     */
    public function handle(StudentActivated $event): void
    {
        $student = $event->student;
        $user = $student->user;
        
        if (!$user || !$user->phone) {
            Log::warning('Cannot send welcome SMS - no phone', [
                'student_id' => $student->id,
                'user_id' => $user->id ?? null,
            ]);
            return;
        }
        
        try {
            // Generate OTP for first login
            $otp = sprintf('%06d', mt_rand(0, 999999));
            $cacheKey = "otp:login:{$user->id}";
            Cache::put($cacheKey, password_hash($otp, PASSWORD_DEFAULT), now()->addMinutes(10));
            
            // Check which SMS provider is enabled
            // MSG91 DLT format: Uses {#var#} format
            // STPL uses {#var#} format
            $isMsg91 = config('services.msg91.enabled') && !config('services.stpl.enabled');
            
            if ($isMsg91) {
                // MSG91 DLT format: Use {#var#} for variables
                $message = "OMAPMS: Welcome to MAP HMS! Your account is activated. Download the app and login with your registered mobile number {#var#}.";
                $message = preg_replace('/\{#var#\}/', $user->phone, $message, 1);
            } else {
                // STPL format: Use generic {#var#} format
                // STPL-approved: "OMAPMS: Welcome to MAP HMS! Your account is activated. Download the app and login with your registered mobile number {#var#}."
                $message = "OMAPMS: Welcome to MAP HMS! Your account is activated. Download the app and login with your registered mobile number {#var#}.";
                // Replace template variable - {#var#} is the phone number
                $message = preg_replace('/\{#var#\}/', $user->phone, $message, 1);
            }
            
            // Send welcome SMS using SmsService
            $smsService = app(SmsService::class);
            $tenantId = $student->tenant_id ?? null;
            
            $smsService->send(
                $user->phone,
                $message,
                $tenantId,
                'student_welcome_otp',
                [
                    'student_id' => $student->id,
                    'user_id' => $user->id,
                ]
            );
            
            // Log audit
            if (class_exists(AuditLogger::class)) {
                app(AuditLogger::class)->logEvent('student.welcome_sent', [
                    'student_id' => $student->id,
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send welcome notification', [
                'student_id' => $student->id,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
