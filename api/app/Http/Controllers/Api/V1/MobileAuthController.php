<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\NormalizesMobilePayload;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Middleware\SetPostgresSessionTenant;

/**
 * Mobile Authentication Controller
 *
 * Handles mobile app authentication flows that require manual tenancy initialization
 * since mobile apps use header-based tenant selection instead of subdomains.
 */
class MobileAuthController extends Controller
{
    use NormalizesMobilePayload;

    /**
     * Send OTP for mobile authentication
     */
    public function sendOtp(Request $request): JsonResponse
    {
        // Normalize payload first to handle malformed JSON
        $this->normalizeMobilePayload($request, ['phone']);

        // Validate phone format (more lenient - allow 0 as first digit)
        try {
            $request->validate([
                'phone' => ['required', 'string', 'regex:/^\+?[0-9]{6,15}$/'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => [
                    'status' => 400,
                    'code' => 'INVALID_PHONE_FORMAT',
                    'title' => 'Invalid Phone Number',
                    'detail' => 'Phone number must be 6-15 digits, optionally prefixed with +.',
                    'validation_errors' => $e->errors(),
                ],
            ], 400);
        }

        $tenantCode = $request->header('X-Tenant-Code');
        $tenant = null;
        
        // If tenant code is provided in header, use it
        if ($tenantCode) {
            $tenant = Tenant::where('code', $tenantCode)->first();
            if (!$tenant) {
                \Log::error('MobileAuthController: Tenant not found', [
                    'tenant_code' => $tenantCode,
                    'phone' => $request->input('phone'),
                ]);
                return response()->json([
                    'errors' => [
                        'status' => 404,
                        'code' => 'TENANT_NOT_FOUND',
                        'title' => 'Invalid Tenant',
                        'detail' => "The specified tenant code '{$tenantCode}' does not exist. Please check your tenant code and try again.",
                    ],
                ], 404);
            }
        } else {
            // Auto-detect tenant from phone number (fallback for apps that don't send header)
            \Log::info('MobileAuthController: X-Tenant-Code header missing, attempting auto-detection from phone', [
                'phone' => $request->input('phone'),
            ]);
            
            $phone = $request->input('phone');
            $normalizedPhone = preg_replace('/\D+/', '', $phone);
            $possiblePhones = array_values(array_unique(array_filter([
                $phone,
                $normalizedPhone,
                str_starts_with($phone, '+') ? ltrim($phone, '+') : null,
                str_starts_with($phone, '+') ? $phone : '+' . $phone,
                $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '+91' . $normalizedPhone : null,
                $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '91' . $normalizedPhone : null,
            ])));
            
            \Log::info('MobileAuthController: Phone normalization', [
                'input_phone' => $phone,
                'normalized_phone' => $normalizedPhone,
                'possible_phones' => $possiblePhones,
            ]);
            
            // Find user by phone - try multiple approaches
            $user = null;
            
            // First try: exact match with possible phone formats
            if (!empty($possiblePhones)) {
                $user = \App\Models\User::query()
                    ->withoutGlobalScopes()
                    ->whereIn('phone', $possiblePhones)
                    ->whereNotNull('tenant_id')
                    ->first();
            }
            
            // Second try: regexp_replace match if first attempt failed
            if (!$user && !empty($normalizedPhone)) {
                $user = \App\Models\User::query()
                    ->withoutGlobalScopes()
                    ->whereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$normalizedPhone])
                    ->whereNotNull('tenant_id')
                    ->first();
            }
            
            // Third try: case-insensitive match (in case of formatting issues)
            if (!$user && !empty($normalizedPhone)) {
                $user = \App\Models\User::query()
                    ->withoutGlobalScopes()
                    ->whereRaw("LOWER(TRIM(phone)) = LOWER(TRIM(?))", [$phone])
                    ->whereNotNull('tenant_id')
                    ->first();
            }
            
            \Log::info('MobileAuthController: User lookup result', [
                'user_found' => $user ? true : false,
                'user_id' => $user?->id,
                'user_phone' => $user?->phone,
                'user_tenant_id' => $user?->tenant_id,
            ]);
            
            if ($user && $user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
                if ($tenant) {
                    \Log::info('MobileAuthController: Auto-detected tenant from phone number', [
                        'phone' => $request->input('phone'),
                        'tenant_id' => $tenant->id,
                        'tenant_code' => $tenant->code,
                        'user_id' => $user->id,
                    ]);
                } else {
                    \Log::error('MobileAuthController: User found but tenant not found', [
                        'user_id' => $user->id,
                        'user_tenant_id' => $user->tenant_id,
                    ]);
                }
            }
            
            if (!$tenant) {
                \Log::error('MobileAuthController: Could not auto-detect tenant from phone number', [
                    'phone' => $request->input('phone'),
                    'normalized_phone' => $normalizedPhone,
                    'possible_phones' => $possiblePhones,
                    'user_found' => $user ? true : false,
                    'user_tenant_id' => $user?->tenant_id,
                ]);
                return response()->json([
                    'errors' => [
                        'status' => 400,
                        'code' => 'TENANT_AUTO_DETECT_FAILED',
                        'title' => 'Tenant Detection Failed',
                        'detail' => 'Could not determine your institution. Please ensure your app is sending the X-Tenant-Code header, or contact support if this issue persists.',
                    ],
                ], 400);
            }
        }

        // Check tenant status before allowing access
        if (!$tenant->canAccess()) {
            return response()->json([
                'errors' => [
                    'status' => 403,
                    'code' => 'TENANT_SUSPENDED',
                    'title' => 'Access Suspended',
                    'detail' => 'Your institution\'s access has been suspended. Please contact support.',
                    'tenant_status' => $tenant->status,
                ],
            ], 403);
        }

        // Initialize tenancy and set PostgreSQL session variable for RLS
        tenancy()->initialize($tenant);
        SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);

        try {
            // Call the actual auth controller
            $authController = new AuthController();
            return $authController->sendOtp($request);
        } catch (\Throwable $e) {
            \Log::error('MobileAuthController::sendOtp failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Always cleanup: clear RLS session variable and end tenancy
            SetPostgresSessionTenant::clearTenantSessionVariable();
            tenancy()->end();
        }
    }

    /**
     * Verify OTP for mobile authentication
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        // Normalize payload first to handle malformed JSON
        $this->normalizeMobilePayload($request, ['phone', 'otp']);

        // Validate request format (more lenient phone validation)
        try {
            $request->validate([
                'phone' => ['required', 'string', 'regex:/^\+?[0-9]{6,15}$/'],
                'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => [
                    'status' => 400,
                    'code' => 'INVALID_REQUEST_FORMAT',
                    'title' => 'Invalid Request',
                    'detail' => 'Phone number must be 6-15 digits (optionally prefixed with +), and OTP must be 6 digits.',
                    'validation_errors' => $e->errors(),
                ],
            ], 400);
        }

        $tenantCode = $request->header('X-Tenant-Code');
        $tenant = null;
        
        // If tenant code is provided in header, use it
        if ($tenantCode) {
            $tenant = Tenant::where('code', $tenantCode)->first();
            if (!$tenant) {
                \Log::error('MobileAuthController: Tenant not found', [
                    'tenant_code' => $tenantCode,
                    'phone' => $request->input('phone'),
                ]);
                return response()->json([
                    'errors' => [
                        'status' => 404,
                        'code' => 'TENANT_NOT_FOUND',
                        'title' => 'Invalid Tenant',
                        'detail' => "The specified tenant code '{$tenantCode}' does not exist. Please check your tenant code and try again.",
                    ],
                ], 404);
            }
        } else {
            // Auto-detect tenant from phone number (fallback for apps that don't send header)
            \Log::info('MobileAuthController: X-Tenant-Code header missing, attempting auto-detection from phone', [
                'phone' => $request->input('phone'),
            ]);
            
            $phone = $request->input('phone');
            $normalizedPhone = preg_replace('/\D+/', '', $phone);
            $possiblePhones = array_values(array_unique(array_filter([
                $phone,
                $normalizedPhone,
                str_starts_with($phone, '+') ? ltrim($phone, '+') : null,
                str_starts_with($phone, '+') ? $phone : '+' . $phone,
                $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '+91' . $normalizedPhone : null,
                $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '91' . $normalizedPhone : null,
            ])));
            
            \Log::info('MobileAuthController: Phone normalization', [
                'input_phone' => $phone,
                'normalized_phone' => $normalizedPhone,
                'possible_phones' => $possiblePhones,
            ]);
            
            // Find user by phone - try multiple approaches
            $user = null;
            
            // First try: exact match with possible phone formats
            if (!empty($possiblePhones)) {
                $user = \App\Models\User::query()
                    ->withoutGlobalScopes()
                    ->whereIn('phone', $possiblePhones)
                    ->whereNotNull('tenant_id')
                    ->first();
            }
            
            // Second try: regexp_replace match if first attempt failed
            if (!$user && !empty($normalizedPhone)) {
                $user = \App\Models\User::query()
                    ->withoutGlobalScopes()
                    ->whereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$normalizedPhone])
                    ->whereNotNull('tenant_id')
                    ->first();
            }
            
            // Third try: case-insensitive match (in case of formatting issues)
            if (!$user && !empty($normalizedPhone)) {
                $user = \App\Models\User::query()
                    ->withoutGlobalScopes()
                    ->whereRaw("LOWER(TRIM(phone)) = LOWER(TRIM(?))", [$phone])
                    ->whereNotNull('tenant_id')
                    ->first();
            }
            
            \Log::info('MobileAuthController: User lookup result', [
                'user_found' => $user ? true : false,
                'user_id' => $user?->id,
                'user_phone' => $user?->phone,
                'user_tenant_id' => $user?->tenant_id,
            ]);
            
            if ($user && $user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
                if ($tenant) {
                    \Log::info('MobileAuthController: Auto-detected tenant from phone number', [
                        'phone' => $request->input('phone'),
                        'tenant_id' => $tenant->id,
                        'tenant_code' => $tenant->code,
                        'user_id' => $user->id,
                    ]);
                } else {
                    \Log::error('MobileAuthController: User found but tenant not found', [
                        'user_id' => $user->id,
                        'user_tenant_id' => $user->tenant_id,
                    ]);
                }
            }
            
            if (!$tenant) {
                \Log::error('MobileAuthController: Could not auto-detect tenant from phone number', [
                    'phone' => $request->input('phone'),
                    'normalized_phone' => $normalizedPhone,
                    'possible_phones' => $possiblePhones,
                    'user_found' => $user ? true : false,
                    'user_tenant_id' => $user?->tenant_id,
                ]);
                return response()->json([
                    'errors' => [
                        'status' => 400,
                        'code' => 'TENANT_AUTO_DETECT_FAILED',
                        'title' => 'Tenant Detection Failed',
                        'detail' => 'Could not determine your institution. Please ensure your app is sending the X-Tenant-Code header, or contact support if this issue persists.',
                    ],
                ], 400);
            }
        }

        // Check tenant status before allowing access
        if (!$tenant->canAccess()) {
            return response()->json([
                'errors' => [
                    'status' => 403,
                    'code' => 'TENANT_SUSPENDED',
                    'title' => 'Access Suspended',
                    'detail' => 'Your institution\'s access has been suspended. Please contact support.',
                    'tenant_status' => $tenant->status,
                ],
            ], 403);
        }

        // Initialize tenancy and set PostgreSQL session variable for RLS
        tenancy()->initialize($tenant);
        SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);

        try {
            // Call the actual auth controller
            $authController = new AuthController();
            return $authController->verifyOtp($request);
        } finally {
            // Always cleanup: clear RLS session variable and end tenancy
            SetPostgresSessionTenant::clearTenantSessionVariable();
            tenancy()->end();
        }
    }
}
