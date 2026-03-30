<?php

namespace App\Services;

use App\Models\User;
use App\Services\Notifications\SmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FilamentOtpService
{
    private const SQLITE_PHONE_DIGITS_EXPR = "replace(replace(replace(replace(replace(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";

    /**
     * Send OTP to user's phone for web panel login
     */
    public function sendOtp(string $phone): array
    {
        // Ensure tenant context is set
        $tenant = tenant();
        
        // If tenant is not set, try to initialize it from the request host
        if (!$tenant) {
            $host = request()->getHost();
            Log::info('FilamentOtpService: Tenant not initialized, attempting manual initialization', [
                'host' => $host,
                'phone' => $phone,
            ]);
            
            // Try to find tenant by domain
            $domain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->first();
            
            if ($domain && $domain->tenant) {
                // Manually initialize tenancy
                tenancy()->initialize($domain->tenant);
                $tenant = tenant();
                
                Log::info('FilamentOtpService: Tenant manually initialized', [
                    'tenant_code' => $tenant->code,
                ]);
            } else {
                Log::error('FilamentOtpService: No tenant found for domain', [
                    'host' => $host,
                    'all_domains' => \Stancl\Tenancy\Database\Models\Domain::pluck('domain')->toArray(),
                ]);
                throw new \Exception('Tenant context required. Access via tenant subdomain.');
            }
        }

        // Normalize phone number (remove non-digits, handle country codes)
        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        $possiblePhones = array_values(array_unique(array_filter([
            $phone,
            $normalizedPhone,
            str_starts_with($phone, '+') ? ltrim($phone, '+') : null,
            str_starts_with($phone, '+') ? $phone : '+' . $phone,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '+91' . $normalizedPhone : null,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '91' . $normalizedPhone : null,
            // Remove country code if present (91 or +91)
            preg_match('/^(\+?91)?(\d{10})$/', $normalizedPhone, $matches) ? ($matches[2] ?? null) : null,
        ])));

        // Find user by phone AND tenant_id (required for RLS and proper isolation).
        $user = User::query()
            ->where(function (Builder $query) use ($possiblePhones, $normalizedPhone) {
                $this->applyPhoneMatchQuery($query, $possiblePhones, $normalizedPhone);
            })
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$user) {
            throw new \Exception('No account exists with this phone number for this tenant');
        }

        // Check if user has web access (Campus Manager, Rector, College Mgmt)
        if (!$this->canAccessWeb($user)) {
            throw new \Exception('You do not have access to the web panel. Please use the mobile app.');
        }

        // Check if user is archived
        if ($user->archived) {
            throw new \Exception('This account has been deactivated');
        }

        $automationSecret = config('otp.automation_secret');
        $requestSecret = request()->header('X-Automation-Secret');
        $isAutomation = $automationSecret && $requestSecret && hash_equals($automationSecret, $requestSecret);

        // Generate OTP (fixed when automation mode enabled)
        $otp = $isAutomation
            ? config('otp.automation_code', '000000')
            : sprintf('%06d', mt_rand(0, 999999));
        
        // Store OTP in cache for 5 minutes (skip if automation mode to avoid cache store issues)
        if (! $isAutomation) {
            $cacheKey = "otp:web:login:{$user->id}";
            Cache::put($cacheKey, password_hash($otp, PASSWORD_DEFAULT), now()->addMinutes(5));
        }

        // Log OTP only in local environment for debugging
        if (app()->environment('local') || $isAutomation) {
            Log::info("Web Panel OTP for {$user->phone} ({$user->name}): {$otp}");
        } else {
            // In production, log without sensitive OTP code
            Log::info("Web Panel OTP sent to user", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'phone_last4' => substr($user->phone, -4),
            ]);
        }
        
        // Send actual SMS via MSG91/STPL (unless in automation mode where SMS is optional)
        if (!$isAutomation) {
            $this->sendOtpSms($user, $otp, $tenant->id);
        } else {
            Log::info("Automation mode: Skipping SMS send", [
                'user_id' => $user->id,
                'phone_last4' => substr($user->phone, -4),
            ]);
        }

        // Don't return OTP in response (security - only log in local/dev)
        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'otp' => app()->environment('local') ? $otp : null, // Local debug only
            'expires_in' => 300,
        ];
    }

    /**
     * Verify OTP and return authenticated user
     */
    public function verifyOtp(string $phone, string $otp): User
    {
        // Ensure tenant context is set
        $tenant = tenant();
        
        // If tenant is not set, try to initialize it from the request host
        if (!$tenant) {
            $host = request()->getHost();
            Log::info('FilamentOtpService: Tenant not initialized during verifyOtp, attempting manual initialization', [
                'host' => $host,
                'phone' => $phone,
            ]);
            
            // Try to find tenant by domain
            $domain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->first();
            
            if ($domain && $domain->tenant) {
                // Manually initialize tenancy
                tenancy()->initialize($domain->tenant);
                $tenant = tenant();
                
                Log::info('FilamentOtpService: Tenant manually initialized in verifyOtp', [
                    'tenant_code' => $tenant->code,
                ]);
            } else {
                Log::error('FilamentOtpService: No tenant found for domain during verifyOtp', [
                    'host' => $host,
                    'all_domains' => \Stancl\Tenancy\Database\Models\Domain::pluck('domain')->toArray(),
                ]);
                throw new \Exception('Tenant context required. Access via tenant subdomain.');
            }
        }

        // Normalize phone number (same as in sendOtp)
        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        $possiblePhones = array_values(array_unique(array_filter([
            $phone,
            $normalizedPhone,
            str_starts_with($phone, '+') ? ltrim($phone, '+') : null,
            str_starts_with($phone, '+') ? $phone : '+' . $phone,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '+91' . $normalizedPhone : null,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '91' . $normalizedPhone : null,
            // Remove country code if present (91 or +91)
            preg_match('/^(\+?91)?(\d{10})$/', $normalizedPhone, $matches) ? ($matches[2] ?? null) : null,
        ])));

        // Find user by phone AND tenant_id (required for RLS and proper isolation).
        $user = User::query()
            ->where(function (Builder $query) use ($possiblePhones, $normalizedPhone) {
                $this->applyPhoneMatchQuery($query, $possiblePhones, $normalizedPhone);
            })
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$user) {
            throw new \Exception('User not found');
        }

        // Check for automation mode (only for automated testing with proper secret)
        $automationSecret = config('otp.automation_secret');
        $requestSecret = request()->header('X-Automation-Secret');
        $isAutomation = $automationSecret && $requestSecret && hash_equals($automationSecret, $requestSecret);

        // Automation bypass for E2E tests only (requires valid secret header)
        if ($isAutomation && $otp === config('otp.automation_code', '000000')) {
            Log::info("Automation OTP verification", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'phone_last4' => substr($user->phone, -4),
            ]);
            
            // Final checks
            if ($user->archived) {
                throw new \Exception('This account has been deactivated');
            }

            if (!$this->canAccessWeb($user)) {
                throw new \Exception('You do not have access to the web panel');
            }

            return $user;
        }

        // Global bypass mode - accepts bypass code for all users (production testing)
        $bypassEnabled = config('otp.bypass_enabled', false)
            || (app()->environment('local')
                && ! config('services.msg91.enabled')
                && ! config('services.stpl.enabled'));
        $bypassCode = config('otp.bypass_code', '123456');
        
        if ($bypassEnabled && $otp === $bypassCode) {
            Log::warning("Web Panel OTP BYPASS used (production testing mode)", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'phone_last4' => substr($user->phone, -4),
                'ip' => request()->ip(),
            ]);
            
            // Final checks
            if ($user->archived) {
                throw new \Exception('This account has been deactivated');
            }

            if (!$this->canAccessWeb($user)) {
                throw new \Exception('You do not have access to the web panel');
            }

            return $user;
        }

        // Verify OTP from cache (production flow)
        $cacheKey = "otp:web:login:{$user->id}";
        $storedHash = Cache::get($cacheKey);

        if (!$storedHash) {
            throw new \Exception('OTP has expired. Please request a new one.');
        }

        if (!password_verify($otp, $storedHash)) {
            throw new \Exception('Invalid OTP. Please try again.');
        }

        // Clear OTP from cache (single use)
        Cache::forget($cacheKey);

        // Final checks
        if ($user->archived) {
            throw new \Exception('This account has been deactivated');
        }

        if (!$this->canAccessWeb($user)) {
            throw new \Exception('You do not have access to the web panel');
        }

        return $user;
    }

    /**
     * Send OTP SMS using SmsService with MSG91/STPL integration
     */
    private function sendOtpSms(User $user, string $otp, string $tenantId): void
    {
        try {
            $smsService = app(SmsService::class);
            
            // Check which SMS provider is enabled
            // MSG91 DLT format: Uses {#var#} format for variables
            // STPL format: Uses {#var#} format for variables
            $isMsg91 = config('services.msg91.enabled') && !config('services.stpl.enabled');
            $expiryMinutes = 5; // Web panel OTP expires in 5 minutes
            
            if ($isMsg91) {
                // MSG91 DLT template format: Message must EXACTLY match approved template
                // IMPORTANT: Check MSG91 dashboard for exact approved template content
                // Common DLT formats: {#var#}, ##var##, or specific variable names
                // The message content must match character-by-character with the approved template
                
                // Try the most common MSG91 DLT format: {#var#}
                // If this doesn't work, check MSG91 dashboard for exact template content
                $message = "Your OMAPMS login code is {#var#}. It expires in {#var#} minutes.";
                // Replace variables - first {#var#} is OTP, second is expiry minutes
                $message = preg_replace('/\{#var#\}/', $otp, $message, 1);
                $message = preg_replace('/\{#var#\}/', (string)$expiryMinutes, $message, 1);
                
                Log::info('FilamentOtpService: MSG91 message format', [
                    'message' => $message,
                    'template_id' => config('services.msg91.templates.otp_login'),
                    'note' => 'If SMS fails, verify exact template content in MSG91 dashboard matches this message',
                ]);
            } else {
                // STPL format: Use generic {#var#} format
                $message = "Your OMAPMS login code is {#var#}. It expires in {#var#} minutes.";
                // Replace template variables - first {#var#} is code, second is expiry minutes
                $message = preg_replace('/\{#var#\}/', $otp, $message, 1);
                $message = preg_replace('/\{#var#\}/', (string)$expiryMinutes, $message, 1);
            }
            
            // Send SMS
            $smsService->send(
                $user->phone,
                $message,
                $tenantId,
                'otp_login',
                [
                    'purpose' => 'web_panel_login',
                    'user_id' => $user->id,
                    'panel' => 'filament',
                ]
            );
            
            Log::info("Web Panel OTP SMS sent successfully", [
                'user_id' => $user->id,
                'phone_last4' => substr($user->phone, -4),
                'tenant_id' => $tenantId,
            ]);
            
        } catch (\Exception $e) {
            // Log error but don't block login flow - user can still try with cached OTP
            Log::error("Failed to send Web Panel OTP SMS", [
                'user_id' => $user->id,
                'phone_last4' => substr($user->phone, -4),
                'error' => $e->getMessage(),
                'note' => 'OTP is cached, user can still attempt login',
            ]);
        }
    }

    /**
     * Check if user can access web panels
     */
    private function canAccessWeb(User $user): bool
    {
        // Check if user has roles that can access web
        return $user->hasAnyRole(['Campus Manager', 'Rector', 'College Management']);
    }

    private function applyPhoneMatchQuery(Builder $query, array $possiblePhones, string $normalizedPhone): void
    {
        if (! empty($possiblePhones)) {
            $query->whereIn('phone', $possiblePhones);
        }

        if (empty($normalizedPhone)) {
            return;
        }

        $digitsOnly = preg_replace('/\D+/', '', $normalizedPhone);

        if (empty($digitsOnly)) {
            return;
        }

        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->orWhereRaw(self::SQLITE_PHONE_DIGITS_EXPR . ' = ?', [$digitsOnly]);

            return;
        }

        // Postgres path.
        $query->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$digitsOnly]);
    }
}

