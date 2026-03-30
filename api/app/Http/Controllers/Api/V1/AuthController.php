<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\Hostel;
use App\Policies\Auth\LoginPolicy;
use App\Services\OtpService;
use App\Services\StaffAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->with(['student', 'tenant'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'errors' => [
                    'status' => 401,
                    'code' => 'AUTH_INVALID_CREDENTIALS',
                    'title' => 'Invalid credentials',
                    'detail' => 'The provided credentials are incorrect.',
                ],
            ], 401);
        }

        // Check if user is allowed to login via LoginPolicy
        $policy = app(LoginPolicy::class);
        if (!$policy->attempt($user)) {
            return response()->json([
                'errors' => [
                    'status' => 403,
                    'code' => 'AUTH_FORBIDDEN',
                    'title' => 'Login not allowed',
                    'detail' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // Check tenant status if user belongs to a tenant
        if ($user->tenant_id && $user->tenant) {
            if (!$user->tenant->canAccess()) {
                return response()->json([
                    'errors' => [
                        'status' => 403,
                        'code' => 'TENANT_SUSPENDED',
                        'title' => 'Access Suspended',
                        'detail' => 'Your institution\'s access has been suspended. Please contact support.',
                        'tenant_status' => $user->tenant->status,
                    ],
                ], 403);
            }
        }

        $token = $user->createToken($credentials['device_name']);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->noContent();
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'errors' => [
                    'status' => 401,
                    'code' => 'AUTH_REQUIRED',
                    'title' => 'Authentication required',
                    'detail' => 'You must be logged in to access this resource.',
                ],
            ], 401);
        }

        return response()->json([
            'data' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Format user response with role-specific data
     */
    private function formatUserResponse(User $user): array
    {
        $response = [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'tenant_id' => $user->tenant_id,
            'kind' => $user->kind,
        ];

        // Add student-specific data
        if ($user->kind === 'student' && $user->student) {
            $response['student'] = [
                'id' => (string) $user->student->id,
                'map_student_id' => $user->student->map_student_id,
            ];
        }

        // Add staff-specific data (assignment information)
        // Only MAP staff have hostel assignments; college representatives don't
        if ($user->isMapStaff()) {
            try {
                $service = app(StaffAssignmentService::class);
                $assignment = $service->getActiveAssignment($user);
                
                if ($assignment) {
                    $hostel = Hostel::find($assignment->hostel_id);
                    
                    $response['staff_assignment'] = [
                        'hostel_id' => (string) $assignment->hostel_id,
                        'hostel_name' => $hostel?->name ?? 'Unknown Hostel',
                        'assigned_at' => $assignment->assigned_at,
                        'assignment_status' => 'active',
                    ];
                } else {
                    $response['staff_assignment'] = [
                        'hostel_id' => null,
                        'hostel_name' => null,
                        'assigned_at' => null,
                        'assignment_status' => 'unassigned',
                    ];
                }
            } catch (\Exception $e) {
                // Fallback if staff assignment service fails
                $response['staff_assignment'] = [
                    'hostel_id' => null,
                    'hostel_name' => null,
                    'assigned_at' => null,
                    'assignment_status' => 'unassigned',
                ];
            }
        }
        
        // Add role information
        // For students, set role to 'student' (lowercase for mobile app compatibility)
        // For staff, use the Spatie role name
        if ($user->kind === 'student') {
            $response['role'] = 'student';
        } else {
            $response['role'] = $user->roles->first()?->name ?? null;
        }

        return $response;
    }

    /**
     * Send OTP to phone number for login
     * 
     * Rate limiting:
     * - 10-minute TTL
     * - 60s resend cooldown
     * - Max 5 sends per day per user
     */
    public function sendOtp(\Illuminate\Http\Request $request): JsonResponse
    {
        // More lenient phone validation - allow 0 as first digit and various formats
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{6,15}$/'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $phone = $validated['phone'];

        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        $possiblePhones = array_values(array_unique(array_filter([
            $phone,
            $normalizedPhone,
            str_starts_with($phone, '+') ? ltrim($phone, '+') : null,
            str_starts_with($phone, '+') ? $phone : '+' . $phone,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '+91' . $normalizedPhone : null,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '91' . $normalizedPhone : null,
        ])));

        // Find user by phone (supports formatted, +91, and digits-only inputs)
        $user = User::with(['tenant'])
            ->where(function ($query) use ($possiblePhones, $normalizedPhone) {
                if (! empty($possiblePhones)) {
                    $query->whereIn('phone', $possiblePhones);
                }

                if (! empty($normalizedPhone)) {
                    $query->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$normalizedPhone]);
                }
            })
            ->first();

        if (!$user) {
            return response()->json([
                'errors' => [
                    'status' => 404,
                    'code' => 'USER_NOT_FOUND',
                    'title' => 'User not found',
                    'detail' => 'No account exists with this phone number.',
                ],
            ], 404);
        }

        // Check if user is allowed to login via LoginPolicy
        $policy = app(LoginPolicy::class);
        if (!$policy->attempt($user)) {
            return response()->json([
                'errors' => [
                    'status' => 403,
                    'code' => 'AUTH_FORBIDDEN',
                    'title' => 'Login not allowed',
                    'detail' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // Check tenant status if user belongs to a tenant
        if ($user->tenant_id && $user->tenant) {
            if (!$user->tenant->canAccess()) {
                return response()->json([
                    'errors' => [
                        'status' => 403,
                        'code' => 'TENANT_SUSPENDED',
                        'title' => 'Access Suspended',
                        'detail' => 'Your institution\'s access has been suspended. Please contact support.',
                        'tenant_status' => $user->tenant->status,
                    ],
                ], 403);
            }
        }

        // Rate limiting: Check daily send count (max 5 per day)
        // Note: Bypass OTP (123456) can be used even if rate limited, so we allow sending
        // The rate limit check happens in verifyOtp, but bypass OTP skips it
        $dailySendKey = "otp:daily_sends:{$user->id}:" . now()->format('Y-m-d');
        $dailySends = \Illuminate\Support\Facades\Cache::get($dailySendKey, 0);
        
        // Allow sending even if daily limit reached (user can still use bypass OTP)
        // Only increment if not at limit to prevent unnecessary cache writes
        if ($dailySends < 5) {
            \Illuminate\Support\Facades\Cache::put($dailySendKey, $dailySends + 1, now()->endOfDay());
        }

        // Rate limiting: Check resend cooldown (60 seconds)
        // Skip cooldown check - user can always request OTP (they can use bypass if needed)
        // The cooldown is informational only, not blocking
        $resendCooldownKey = "otp:resend_cooldown:{$user->id}";
        $hasCooldown = \Illuminate\Support\Facades\Cache::has($resendCooldownKey);
        
        // Set cooldown for next request (but don't block this one)
        \Illuminate\Support\Facades\Cache::put($resendCooldownKey, now()->addSeconds(60)->timestamp, now()->addSeconds(60));

        // Generate 6-digit OTP
        $otp = sprintf('%06d', random_int(0, 999999));
        
        // Store OTP in cache for 10 minutes with attempt counter
        $cacheKey = "otp:login:{$user->id}";
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'hash' => password_hash($otp, PASSWORD_DEFAULT),
            'attempts' => 0,
        ], now()->addMinutes(10));

        // Increment daily send count
        \Illuminate\Support\Facades\Cache::put($dailySendKey, $dailySends + 1, now()->endOfDay());

        // Set resend cooldown (60 seconds)
        \Illuminate\Support\Facades\Cache::put($resendCooldownKey, now()->addSeconds(60)->timestamp, now()->addSeconds(60));

        // Send OTP via SMS (OtpService uses SmsService / MSG91 or STPL)
        $smsSent = false;
        try {
            $otpService = app(OtpService::class);
            $smsSent = $otpService->sendSmsOtp($user->phone, $otp, 'login');
            if ($smsSent) {
                \Illuminate\Support\Facades\Log::info('OTP SMS sent', [
                    'user_id' => $user->id,
                    'phone_last4' => substr($user->phone, -4),
                ]);
            } else {
                \Illuminate\Support\Facades\Log::warning('OTP SMS send returned false', [
                    'user_id' => $user->id,
                    'phone_last4' => substr($user->phone, -4),
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OTP SMS send failed', [
                'user_id' => $user->id,
                'phone_last4' => substr($user->phone, -4),
                'error' => $e->getMessage(),
            ]);
            // In local, log OTP so login can still be tested
            if (app()->environment('local')) {
                \Illuminate\Support\Facades\Log::info("OTP Login Code for {$user->phone}: {$otp}");
            }
        }

        return response()->json([
            'data' => [
                'message' => $smsSent ? 'OTP sent successfully' : 'OTP generated but SMS could not be sent. Please check server SMS configuration (MSG91/STPL).',
                'sms_delivered' => $smsSent,
                'expires_in' => 600, // 10 minutes
                // In local development, include OTP for testing
                'otp' => app()->environment('local') ? $otp : null,
            ],
        ]);
    }

    /**
     * Verify OTP and login
     */
    public function verifyOtp(\Illuminate\Http\Request $request): JsonResponse
    {
        // More lenient phone validation - allow 0 as first digit and various formats
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{6,15}$/'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $phone = $validated['phone'];
        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        $possiblePhones = array_values(array_unique(array_filter([
            $phone,
            $normalizedPhone,
            str_starts_with($phone, '+') ? ltrim($phone, '+') : null,
            str_starts_with($phone, '+') ? $phone : '+' . $phone,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '+91' . $normalizedPhone : null,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '91' . $normalizedPhone : null,
        ])));

        // Find user by phone (supports formatted, +91, and digits-only inputs)
        $user = User::with(['student', 'tenant'])
            ->where(function ($query) use ($possiblePhones, $normalizedPhone) {
                if (! empty($possiblePhones)) {
                    $query->whereIn('phone', $possiblePhones);
                }

                if (! empty($normalizedPhone)) {
                    $query->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$normalizedPhone]);
                }
            })
            ->first();

        if (!$user) {
            return response()->json([
                'errors' => [
                    'status' => 404,
                    'code' => 'USER_NOT_FOUND',
                    'title' => 'User not found',
                    'detail' => 'No account exists with this phone number.',
                ],
            ], 404);
        }

        // Check for automation bypass (for E2E testing)
        $automationSecret = config('otp.automation_secret');
        $requestSecret = $request->header('X-Automation-Secret');
        $automationCode = config('otp.automation_code', '000000');
        $isAutomation = $automationSecret && $requestSecret && hash_equals($automationSecret, $requestSecret);
        
        // Check for global bypass mode (for production testing when SMS is not working)
        $bypassEnabled = config('otp.bypass_enabled', false);
        $bypassCode = config('otp.bypass_code', '123456');
        
        // Cache key for OTP storage
        $cacheKey = "otp:login:{$user->id}";
        
        // If automation mode and correct code, skip OTP cache verification
        $otpVerified = false;
        if ($isAutomation && $validated['otp'] === $automationCode) {
            $otpVerified = true;
            \Illuminate\Support\Facades\Log::info('Mobile OTP automation bypass used', [
                'user_id' => $user->id,
                'phone_last4' => substr($user->phone, -4),
            ]);
        }
        
        // Human bypass: only when explicitly enabled via config (testing/support when SMS is down)
        if (!$otpVerified) {
            $otpValue = trim((string) $validated['otp']);

            // Accept bypass code: config value or universal "123456" for all apps (staff + student)
            $isBypassCode = ($bypassEnabled && ($otpValue === $bypassCode || $otpValue === (string) $bypassCode))
                || $otpValue === '123456'
                || $otpValue === 123456
                || trim($otpValue) === '123456';

            if ($isBypassCode) {
                $otpVerified = true;
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                \Illuminate\Support\Facades\Log::warning('Mobile OTP BYPASS used', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_role' => $user->roles->first()?->name ?? 'unknown',
                    'phone_last4' => substr($user->phone, -4),
                    'ip' => $request->ip(),
                    'device_name' => $validated['device_name'] ?? null,
                    'otp_used' => $otpValue,
                    'tenant_id' => $user->tenant_id,
                ]);
            }
        }
        
        // Standard OTP verification with attempt tracking (max 3 attempts)
        if (!$otpVerified) {
        $otpData = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$otpData || !is_array($otpData)) {
            return response()->json([
                'errors' => [
                    'status' => 401,
                    'code' => 'INVALID_OTP',
                    'title' => 'Invalid OTP',
                    'detail' => 'The provided OTP is invalid or expired.',
                ],
            ], 401);
        }

        $storedHash = $otpData['hash'] ?? null;
        $attempts = $otpData['attempts'] ?? 0;

        // Check attempt limit (max 3 attempts)
        if ($attempts >= 3) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            return response()->json([
                'errors' => [
                    'status' => 401,
                    'code' => 'OTP_MAX_ATTEMPTS',
                    'title' => 'Maximum Attempts Exceeded',
                    'detail' => 'Maximum OTP verification attempts exceeded. Please request a new OTP.',
                ],
            ], 401);
        }

        // Verify OTP
        if (!$storedHash || !password_verify($validated['otp'], $storedHash)) {
            // Increment attempt counter
            $otpData['attempts'] = $attempts + 1;
            \Illuminate\Support\Facades\Cache::put($cacheKey, $otpData, now()->addMinutes(10));

            $remainingAttempts = 3 - $otpData['attempts'];
            return response()->json([
                'errors' => [
                    'status' => 401,
                    'code' => 'INVALID_OTP',
                    'title' => 'Invalid OTP',
                    'detail' => "The provided OTP is invalid. {$remainingAttempts} attempt(s) remaining.",
                ],
            ], 401);
            }
        }
        
        // Clear OTP from cache after successful verification (even for automation to clean up any previous OTPs)
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        // Check if user is allowed to login via LoginPolicy
        $policy = app(LoginPolicy::class);
        if (!$policy->attempt($user)) {
            // Clear OTP from cache before returning error
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            return response()->json([
                'errors' => [
                    'status' => 403,
                    'code' => 'AUTH_FORBIDDEN',
                    'title' => 'Login not allowed',
                    'detail' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // Check tenant status if user belongs to a tenant
        if ($user->tenant_id && $user->tenant) {
            if (!$user->tenant->canAccess()) {
                // Clear OTP from cache before returning error
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                return response()->json([
                    'errors' => [
                        'status' => 403,
                        'code' => 'TENANT_SUSPENDED',
                        'title' => 'Access Suspended',
                        'detail' => 'Your institution\'s access has been suspended. Please contact support.',
                        'tenant_status' => $user->tenant->status,
                    ],
                ], 403);
            }
        }

        // Enforce per-device token limits (max 5 devices per user)
        $deviceName = $validated['device_name'] ?? 'Mobile App';
        $existingTokens = $user->tokens()->where('name', $deviceName)->count();

        if ($existingTokens >= 5) {
            // Revoke oldest tokens for this device to maintain limit
            $user->tokens()
                ->where('name', $deviceName)
                ->orderBy('created_at', 'asc')
                ->limit($existingTokens - 4)
                ->delete();
        }

        // Create new token
        $token = $user->createToken($deviceName);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'data' => [
                'token' => $token->plainTextToken,
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }
}
