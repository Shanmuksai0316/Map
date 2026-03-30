<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Demo Session Controller
 * 
 * Issues short-lived demo tokens for client demonstrations.
 * Protected by HMAC signature verification and demo mode flag.
 */
class DemoSessionController extends Controller
{
    /**
     * Role mapping from demo role names to actual role names
     */
    private function mapRole(string $demoRole): string
    {
        $roleMap = [
            'campus_manager' => 'Campus Manager',
            'rector' => 'Rector',
            'warden' => 'Warden',
            'guard' => 'Guard',
            'hk_supervisor' => 'HK Supervisor',
            'rm_supervisor' => 'RM Supervisor',
            'laundry_manager' => 'Laundry Manager',
            'sports_manager' => 'Sports Manager',
            'student' => 'Student',
        ];

        return $roleMap[$demoRole] ?? 'Campus Manager';
    }

    /**
     * Issue demo token for a specific role
     */
    public function issue(Request $request): JsonResponse
    {
        // Check if demo mode is enabled
        if (!config('app.demo_mode', false)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Demo Mode Disabled',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Demo mode is not enabled on this server.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate request
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:campus_manager,rector,warden,guard,hk_supervisor,rm_supervisor,laundry_manager,sports_manager,student'],
            'nonce' => ['required', 'string', 'size:32'], // 32-char nonce
            'sig' => ['required', 'string'], // HMAC signature
        ]);

        // Verify HMAC signature
        $tenant = tenant();
        if (!$tenant) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'Request must be made within a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $hmacSecret = config('demo.hmac_secret');
        if (!$hmacSecret) {
            Log::error('DEMO_HMAC_SECRET not configured');
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Configuration Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Demo mode is not properly configured.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Build signature payload: tenant_id|role|nonce|timestamp (if using timestamp)
        $payload = $tenant->id . '|' . $validated['role'] . '|' . $validated['nonce'];
        $expectedSig = hash_hmac('sha256', $payload, $hmacSecret);

        if (!hash_equals($expectedSig, $validated['sig'])) {
            Log::warning('Demo token request with invalid signature', [
                'tenant_id' => $tenant->id,
                'role' => $validated['role'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Invalid Signature',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Request signature verification failed.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Find user with the requested role in this tenant
        $roleName = $this->mapRole($validated['role']);
        $user = User::role($roleName)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->first();

        if (!$user) {
            Log::warning('Demo token request for non-existent role', [
                'tenant_id' => $tenant->id,
                'role' => $roleName,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Role Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => "No active user found with role '{$roleName}' in this tenant.",
            ], Response::HTTP_NOT_FOUND);
        }

        // Create token with limited abilities (read-only for safety)
        $abilities = [
            'read:dashboard',
            'read:tickets',
            'read:notices',
            'read:attendance',
            'read:students',
            'read:outpasses',
            'read:reports',
        ];

        // Add role-specific write abilities for demo purposes
        if (in_array($validated['role'], ['campus_manager', 'rector'])) {
            $abilities[] = 'write:outpasses'; // Approve/reject
        }
        if ($validated['role'] === 'warden') {
            $abilities[] = 'write:attendance';
        }
        if (in_array($validated['role'], ['hk_supervisor', 'rm_supervisor'])) {
            $abilities[] = 'write:tickets';
        }
        if ($validated['role'] === 'student') {
            // Student-specific write abilities
            $abilities[] = 'write:gate-passes'; // Create gate passes
            $abilities[] = 'write:tickets'; // Create tickets/complaints
            $abilities[] = 'write:leaves'; // Create leave requests
            $abilities[] = 'write:guest-entries'; // Create guest entries
            $abilities[] = 'write:room-changes'; // Create room change requests
            $abilities[] = 'write:sports-bookings'; // Book sports facilities
            $abilities[] = 'write:laundry-requests'; // Create laundry requests
        }

        $tokenName = $validated['role'] === 'student' ? 'demo-student-app' : 'demo-staff-app';
        $token = $user->createToken($tokenName, $abilities, now()->addHours(2));

        Log::info('Demo token issued', [
            'user_id' => $user->id,
            'role' => $roleName,
            'tenant_id' => $tenant->id,
            'expires_at' => now()->addHours(2)->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'role' => $validated['role'],
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                    'domain' => $tenant->subdomain ?? $tenant->domain ?? null,
                    'apiUrl' => url('/v1'),
                ],
                'expires_at' => now()->addHours(2)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Generate nonce for client-side signature generation
     */
    public function nonce(): JsonResponse
    {
        if (!config('app.demo_mode', false)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Demo Mode Disabled',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Demo mode is not enabled on this server.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tenant = tenant();
        if (!$tenant) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'Request must be made within a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $nonce = bin2hex(random_bytes(16)); // 32-char hex string

        return response()->json([
            'data' => [
                'nonce' => $nonce,
                'tenant_id' => $tenant->id,
                'tenant_code' => $tenant->code,
                'expires_in' => 300, // 5 minutes
            ],
        ]);
    }
}

