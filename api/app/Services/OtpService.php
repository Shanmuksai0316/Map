<?php

namespace App\Services;

use App\Services\Notifications\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function start(int $userId, string $purpose, string $channel, string $to, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        $key = $this->cacheKey($userId, $purpose, $tenantId);
        
        // Check for rate limiting and lockout
        $attempts = Cache::get("$key:attempts", 0);
        if ($attempts >= 5) {
            throw ValidationException::withMessages([
                'otp' => ['Too many attempts. Please try again later.']
            ]);
        }
        
        // Increment attempt counter
        Cache::increment("$key:attempts");
        Cache::put("$key:attempts", $attempts + 1, now()->addMinutes(15));

        $code = (string) random_int(100000, 999999);
        $hashedCode = password_hash($code, PASSWORD_BCRYPT);
        
        // Store hashed code with 10-minute expiry
        Cache::put("$key:code", $hashedCode, now()->addMinutes(10));

        // Send OTP via appropriate channel
        $sent = $this->sendOtp($channel, $to, $code, $purpose);

        // Return response based on environment
        if (app()->isProduction()) {
            return ['sent' => $sent];
        } else {
            return [
                'sent' => $sent,
                'debug_code' => $code, // Only in non-production
            ];
        }
    }

    public function verify(int $userId, string $purpose, string $code, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        $key = $this->cacheKey($userId, $purpose, $tenantId);
        $hashedCode = Cache::get("$key:code");
        
        if (!$hashedCode) {
            return false;
        }
        
        $isValid = password_verify($code, $hashedCode);
        
        if ($isValid) {
            // Clear the OTP code and attempts
            Cache::forget("$key:code");
            Cache::forget("$key:attempts");
            
            // Set recent verification flag
            Cache::put($this->recentKey($userId, $purpose, $tenantId), 1, now()->addMinutes(10));
            
            Log::info('OTP verified successfully', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'purpose' => $purpose,
            ]);
        } else {
            Log::warning('OTP verification failed', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'purpose' => $purpose,
            ]);
        }
        
        return $isValid;
    }

    public function recentlyVerified(int $userId, string $purpose, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        return (bool) Cache::get($this->recentKey($userId, $purpose, $tenantId), false);
    }

    public function getRemainingAttempts(int $userId, string $purpose, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        $key = $this->cacheKey($userId, $purpose, $tenantId);
        $attempts = Cache::get("$key:attempts", 0);
        return max(0, 5 - $attempts);
    }

    public function clearAttempts(int $userId, string $purpose, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        $key = $this->cacheKey($userId, $purpose, $tenantId);
        Cache::forget("$key:attempts");
    }

    private function sendOtp(string $channel, string $to, string $code, string $purpose): bool
    {
        switch ($channel) {
            case 'sms':
                return $this->sendSmsOtp($to, $code, $purpose);
            case 'email':
                return $this->sendEmailOtp($to, $code, $purpose);
            default:
                Log::info('OTP noop', compact('to', 'purpose', 'code'));
                return true; // For testing/development
        }
    }

    /**
     * Send SMS OTP (public method for direct OTP sending)
     */
    public function sendSmsOtp(string $to, string $code, string $purpose): bool
    {
        $tenantId = $this->getCurrentTenantId();
        $smsService = app(\App\Services\Notifications\SmsService::class);
        
        // Map purpose to template name
        $template = match($purpose) {
            'login' => 'otp_login',
            'welcome' => 'student_welcome_otp',
            default => 'otp_login'
        };
        
        // Check which SMS provider is enabled
        // MSG91 uses ##otp##, ##minutes## format
        // STPL uses {#var#} format
        $isMsg91 = config('services.msg91.enabled') && !config('services.stpl.enabled');
        $expiryMinutes = 10;
        
        if ($isMsg91) {
            // MSG91 DLT format: Use {#var#} for variables (DLT compliant format)
            $message = match($purpose) {
                'login' => "Your OMAPMS login code is {#var#}. It expires in {#var#} minutes.",
                'welcome' => "OMAPMS: Welcome to MAP HMS! Your account is activated. Download the app and login with your registered mobile number {#var#}.",
                default => "Your OMAPMS login code is {#var#}. It expires in {#var#} minutes."
            };
            
            // Replace MSG91 DLT variables ({#var#} format)
            if ($purpose === 'welcome') {
                // Welcome message has only one variable (phone number)
                $message = preg_replace('/\{#var#\}/', $to, $message, 1);
            } else {
                // Login messages have two variables: OTP code and expiry minutes
                $message = preg_replace('/\{#var#\}/', $code, $message, 1);
                $message = preg_replace('/\{#var#\}/', (string)$expiryMinutes, $message, 1);
            }
        } else {
            // STPL format: Use generic {#var#} format
            $message = match($purpose) {
                'login' => "Your OMAPMS login code is {#var#}. It expires in {#var#} minutes.",
                'welcome' => "OMAPMS: Welcome to MAP HMS! Your account is activated. Download the app and login with your registered mobile number {#var#}.",
                default => "Your OMAPMS login code is {#var#}. It expires in {#var#} minutes."
            };
            
            // Replace STPL variables - {#var#} format
            // For login: first {#var#} is code, second is expiry minutes
            // For welcome: {#var#} is phone number
            if ($purpose === 'welcome') {
                $message = preg_replace('/\{#var#\}/', $to, $message, 1);
            } else {
                // Replace first {#var#} with code, second with expiry minutes
                $message = preg_replace('/\{#var#\}/', $code, $message, 1);
                $message = preg_replace('/\{#var#\}/', (string)$expiryMinutes, $message, 1);
            }
        }
        
        $result = $smsService->send($to, $message, $tenantId, $template, [
            'purpose' => $purpose,
            'code_length' => strlen($code)
        ]);
        return $result;
    }

    private function sendEmailOtp(string $to, string $code, string $purpose): bool
    {
        // TODO: Implement email OTP sending
        Log::info('Email OTP', [
            'to' => $to,
            'purpose' => $purpose,
            'code' => app()->isProduction() ? '[MASKED]' : $code,
        ]);

        return true;
    }

    private function cacheKey(int $userId, string $purpose, ?string $tenantId): string 
    { 
        $tenantId = $tenantId ?? 'central';
        return "otp:$tenantId:$userId:$purpose"; 
    }
    
    private function recentKey(int $userId, string $purpose, ?string $tenantId): string 
    { 
        $tenantId = $tenantId ?? 'central';
        return "otpok:$tenantId:$userId:$purpose"; 
    }

    /**
     * Get current tenant ID from tenancy context
     */
    private function getCurrentTenantId(): ?string
    {
        try {
            $tenant = tenant();
            return $tenant ? $tenant->id : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
