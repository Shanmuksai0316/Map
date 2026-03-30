<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantFeatureFlag;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlagsController extends Controller
{
    /**
     * Get tenant feature flags.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenancy()->tenant?->id;

        if (!$tenantId) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'This action requires a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $flags = TenantFeatureFlag::getTenantFlags($tenantId);

            // Flatten response to match mobile schema: { feature_key_enabled: true }
            // API returns: { feature_key: { enabled: true, ... } }
            // Mobile expects: { feature_key_enabled: true, ... }
            $flattenedFlags = [];
            foreach ($flags as $featureKey => $flagData) {
                // Convert feature_key to feature_key_enabled format
                $flatKey = $featureKey . '_enabled';
                $flattenedFlags[$flatKey] = $flagData['enabled'] ?? false;
            }

            return response()->json([
                'data' => $flattenedFlags,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to get tenant feature flags', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/feature_flags_fetch_failed',
                'title' => 'Feature Flags Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve feature flags. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update tenant feature flags.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flags' => 'required|array',
            'flags.*.key' => 'required|string',
            'flags.*.enabled' => 'required|boolean',
            'flags.*.config' => 'sometimes|array',
            'notes' => 'sometimes|string|max:500',
        ]);

        $user = $request->user();
        $tenantId = tenancy()->tenant?->id;

        if (!$tenantId) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'This action requires a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if user has permission to update feature flags
        if (!$this->canUpdateFeatureFlags($user)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/insufficient_permissions',
                'title' => 'Insufficient Permissions',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'You do not have permission to update feature flags.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $updatedFlags = [];
            $notes = $validated['notes'] ?? 'Updated via API';

            foreach ($validated['flags'] as $flagData) {
                $flag = TenantFeatureFlag::setTenantFlag(
                    $tenantId,
                    $flagData['key'],
                    $flagData['enabled'],
                    $flagData['config'] ?? null,
                    $user->id,
                    $notes
                );

                $updatedFlags[$flag->feature_key] = [
                    'enabled' => $flag->is_enabled,
                    'config' => $flag->config,
                    'enabled_at' => $flag->enabled_at,
                    'notes' => $flag->notes,
                ];
            }

            Log::info('Feature flags updated', [
                'tenant_id' => $tenantId,
                'updated_by' => $user->id,
                'flags_updated' => count($updatedFlags),
            ]);

            return response()->json([
                'message' => 'Feature flags updated successfully',
                'data' => $updatedFlags,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to update feature flags', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/feature_flags_update_failed',
                'title' => 'Feature Flags Update Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to update feature flags. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get default feature flags for a new tenant.
     */
    public function defaults(): JsonResponse
    {
        try {
            $defaultFlags = TenantFeatureFlag::getDefaultFlags();

            return response()->json([
                'data' => $defaultFlags,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to get default feature flags', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/default_flags_fetch_failed',
                'title' => 'Default Flags Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve default feature flags.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Initialize feature flags for a new tenant.
     */
    public function initialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|string',
        ]);

        $user = $request->user();

        // Check if user has permission to initialize feature flags
        if (!$this->canInitializeFeatureFlags($user)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/insufficient_permissions',
                'title' => 'Insufficient Permissions',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'You do not have permission to initialize feature flags.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            TenantFeatureFlag::initializeTenantFlags($validated['tenant_id'], $user->id);

            Log::info('Feature flags initialized for new tenant', [
                'tenant_id' => $validated['tenant_id'],
                'initialized_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Feature flags initialized successfully',
                'data' => [
                    'tenant_id' => $validated['tenant_id'],
                    'initialized_at' => now(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to initialize feature flags', [
                'error' => $e->getMessage(),
                'tenant_id' => $validated['tenant_id'],
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/feature_flags_initialization_failed',
                'title' => 'Feature Flags Initialization Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to initialize feature flags. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if user can update feature flags.
     */
    private function canUpdateFeatureFlags($user): bool
    {
        // Only super admins and campus managers can update feature flags
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::CAMPUS_MANAGER]);
    }

    /**
     * Check if user can initialize feature flags.
     */
    private function canInitializeFeatureFlags($user): bool
    {
        // Only super admins can initialize feature flags
        return $user->hasRole(Roles::SUPER_ADMIN);
    }
}
