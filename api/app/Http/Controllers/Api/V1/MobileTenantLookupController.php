<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\NormalizesMobilePayload;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileTenantLookupController extends Controller
{
    use NormalizesMobilePayload;

    /**
     * Lookup tenant metadata for a student phone number so the mobile
     * client can auto-select the correct tenant before OTP login.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->normalizeMobilePayload($request, ['phone']);

        $validated = $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^\+?[1-9]\d{6,14}$/',
            ],
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

        Log::debug('Mobile tenant lookup', [
            'input_phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'possible_phones' => $possiblePhones,
        ]);

        /** @var User|null $user */
        // First try to find a student user
        $user = User::query()
            ->withoutGlobalScopes()
            ->where(function ($query) use ($possiblePhones, $normalizedPhone) {
                if (! empty($possiblePhones)) {
                    $query->whereIn('phone', $possiblePhones);
                }

                if (! empty($normalizedPhone)) {
                    $query->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$normalizedPhone]);
                }
            })
            ->whereRaw('LOWER(kind) = ?', ['student'])
            ->whereNotNull('tenant_id')
            ->first();
        
        // If no student found, try to find a staff user (any non-student kind)
        if (! $user) {
            $user = User::query()
                ->withoutGlobalScopes()
                ->where(function ($query) use ($possiblePhones, $normalizedPhone) {
                    if (! empty($possiblePhones)) {
                        $query->whereIn('phone', $possiblePhones);
                    }

                    if (! empty($normalizedPhone)) {
                        $query->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$normalizedPhone]);
                    }
                })
                ->whereRaw('LOWER(kind) != ?', ['student'])
                ->whereNotNull('tenant_id')
                ->first();
        }

        if (! $user) {
            // Log for debugging - check if user exists without the kind/tenant filters
            $anyUser = User::query()
                ->withoutGlobalScopes()
                ->where(function ($query) use ($possiblePhones, $normalizedPhone) {
                    if (! empty($possiblePhones)) {
                        $query->whereIn('phone', $possiblePhones);
                    }

                    if (! empty($normalizedPhone)) {
                        $query->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$normalizedPhone]);
                    }
                })
                ->first();

            if ($anyUser) {
                Log::warning('User found but filtered out', [
                    'user_id' => $anyUser->id,
                    'phone' => $anyUser->phone,
                    'kind' => $anyUser->kind,
                    'tenant_id' => $anyUser->tenant_id,
                ]);
            } else {
                Log::warning('No user found with phone number', [
                    'input_phone' => $phone,
                    'normalized_phone' => $normalizedPhone,
                    'possible_phones' => $possiblePhones,
                ]);
            }
        }

        if (! $user) {
            return response()->json([
                'errors' => [
                    'status' => 404,
                    'code' => 'USER_NOT_FOUND',
                    'title' => 'User not found',
                    'detail' => 'No account exists with this phone number.',
                ],
            ], 404);
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->with('domains')->find($user->tenant_id);

        if (! $tenant) {
            return response()->json([
                'errors' => [
                    'status' => 404,
                    'code' => 'TENANT_NOT_FOUND',
                    'title' => 'Tenant not found',
                    'detail' => 'The institution for this student no longer exists.',
                ],
            ], 404);
        }

        if (! $tenant->canAccess()) {
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

        $student = Student::query()
            ->with('hostel')
            ->where('user_id', (string) $user->id)
            ->first();

        $primaryDomain = $tenant->primary_domain;

        return response()->json([
            'data' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_code' => $tenant->code,
                'tenant_name' => $tenant->name,
                'tenant_status' => $tenant->status,
                'domain' => $primaryDomain,
                'api_url' => config('services.mobile_central_api', config('app.url') . '/api/v1'),
                'hostel_id' => optional($student)->hostel_id,
                'hostel_name' => optional($student?->hostel)->name,
            ],
        ]);
    }
}

