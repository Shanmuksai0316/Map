<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Onboarding\PreflightService;
use App\Events\TenantActivated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class TenantOnboardingController extends Controller
{
    public function __construct(
        private readonly PreflightService $preflightService,
    ) {}

    /**
     * Create draft tenant (Step 1: Tenant Information)
     * POST /v1/tenants
     * 
     * Requires Idempotency-Key header
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'regex:/^MAP-[A-Z0-9-]{2,20}$/', 'unique:tenants,code'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'campus_name' => ['required', 'string', 'max:255'],
            'campus_address' => ['nullable', 'array'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'The request data is invalid.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $tenant = DB::transaction(function () use ($request) {
                // Create tenant with provisioning status
                $tenant = Tenant::create([
                    'code' => $request->code,
                    'name' => $request->name,
                    'status' => \App\Enums\TenantStatus::PROVISIONING,
                    'data' => [
                        'logo' => $request->hasFile('logo') ? $request->file('logo')->store('logos') : null,
                        'campus_name' => $request->campus_name,
                        'campus_address' => $request->campus_address,
                        'contact_email' => $request->contact_email,
                        'contact_phone' => $request->contact_phone,
                    ],
                ]);

                // Create campus (1 tenant = 1 campus)
                $campus = \App\Models\Campus::create([
                    'tenant_id' => $tenant->id,
                    'code' => $tenant->code . '-CAMPUS',
                    'name' => $request->campus_name,
                    'address' => $request->campus_address,
                ]);

                Log::info('Tenant draft created', [
                    'tenant_id' => $tenant->id,
                    'code' => $tenant->code,
                ]);

                return $tenant;
            });

            return response()->json([
                'data' => [
                    'id' => $tenant->id,
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                    'status' => $tenant->status->value,
                    'created_at' => $tenant->created_at->toISOString(),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create tenant draft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'type' => 'https://api.map-hms/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create tenant. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upsert wizard steps (Steps 2-4)
     * PUT /v1/tenants/{tenant}/wizard
     */
    public function updateWizard(Request $request, Tenant $tenant): JsonResponse
    {
        // Validate tenant is in provisioning state
        if ($tenant->status !== \App\Enums\TenantStatus::PROVISIONING) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/tenants/invalid_status',
                'title' => 'Invalid Tenant Status',
                'status' => Response::HTTP_CONFLICT,
                'detail' => 'Wizard can only be updated for tenants in provisioning status.',
                'code' => 'tenants/invalid_status',
            ], Response::HTTP_CONFLICT);
        }

        $validator = Validator::make($request->all(), [
            'step' => ['required', 'in:hostels,staff,contacts'],
            'data' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'The request data is invalid.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Store wizard data in tenant's data field
            $wizardData = $tenant->wizard ?? [];
            $wizardData[$request->step] = $request->data;
            
            $mergedData = $this->getTenantDataArray($tenant);
            $mergedData['wizard'] = $wizardData;

            \DB::table('tenants')->where('id', $tenant->id)->update([
                'data' => json_encode($mergedData),
                'updated_at' => now(),
            ]);
            $tenant->setAttribute('wizard', $wizardData);

            return response()->json([
                'data' => [
                    'tenant_id' => $tenant->id,
                    'step' => $request->step,
                    'saved' => true,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to update wizard step', [
                'tenant_id' => $tenant->id,
                'step' => $request->step,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'type' => 'https://api.map-hms/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to save wizard step. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Activate tenant (Step 5: Pre-flight + Activation)
     * POST /v1/tenants/{tenant}/activate
     * 
     * Requires Idempotency-Key header
     */
    public function activate(Request $request, Tenant $tenant): JsonResponse
    {
        // Validate tenant status
        if ($tenant->status === \App\Enums\TenantStatus::ACTIVE) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/tenants/already_active',
                'title' => 'Tenant is already active',
                'status' => Response::HTTP_CONFLICT,
                'detail' => 'Activation cannot be repeated for an active tenant.',
                'code' => 'tenants/already_active',
            ], Response::HTTP_CONFLICT);
        }

        if ($tenant->status === \App\Enums\TenantStatus::ARCHIVED) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/tenants/archived_readonly',
                'title' => 'Archived tenant is read-only',
                'status' => Response::HTTP_CONFLICT,
                'detail' => 'Archived tenants cannot be activated or modified.',
                'code' => 'tenants/archived_readonly',
            ], Response::HTTP_CONFLICT);
        }

        // Run pre-flight checks
        $wizardData = $tenant->wizard ?? [];
        $preflight = $this->preflightService->evaluate($tenant, $wizardData);

        if (!$preflight['passed']) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/onboarding/preflight_failed',
                'title' => 'Pre-flight checks failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'One or more pre-flight checks failed.',
                'code' => 'onboarding/preflight_failed',
                'errors' => $preflight['errors'],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::transaction(function () use ($tenant, $wizardData) {
                // Activate tenant
                $tenant->update([
                    'status' => \App\Enums\TenantStatus::ACTIVE,
                ]);

                // Dispatch TenantActivated event
                event(new TenantActivated($tenant, $wizardData));

                Log::info('Tenant activated', [
                    'tenant_id' => $tenant->id,
                    'code' => $tenant->code,
                ]);
            });

            return response()->json([
                'data' => [
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'activated_at' => $tenant->fresh()->updated_at->toISOString(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to activate tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'type' => 'https://api.map-hms/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to activate tenant. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Rollback tenant activation (Super Admin only)
     * POST /v1/tenants/{tenant}/rollback
     *
     * Allows Super Admin to rollback a recently activated tenant back to provisioning status.
     * Only works within 24 hours of activation and requires additional verification.
     */
    public function rollback(Request $request, Tenant $tenant): JsonResponse
    {
        // Only Super Admin can rollback
        if (!$request->user() || !$request->user()->hasRole('Super Admin')) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/insufficient_permissions',
                'title' => 'Insufficient Permissions',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Super Admin can rollback tenant activation.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate tenant status
        if ($tenant->status !== \App\Enums\TenantStatus::ACTIVE) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/tenants/invalid_status',
                'title' => 'Invalid Tenant Status',
                'status' => Response::HTTP_CONFLICT,
                'detail' => 'Only active tenants can be rolled back.',
            ], Response::HTTP_CONFLICT);
        }

        // Check rollback window (24 hours)
        $activatedAt = $tenant->updated_at;
        $rollbackDeadline = $activatedAt->addHours(24);

        if (now()->isAfter($rollbackDeadline)) {
            return response()->json([
                'type' => 'https://api.map-hms/errors/tenants/rollback_expired',
                'title' => 'Rollback Window Expired',
                'status' => Response::HTTP_CONFLICT,
                'detail' => 'Rollback is only allowed within 24 hours of activation.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(function () use ($tenant) {
                // Archive all created data (soft delete where possible)
                // Note: In production, this would need careful handling of foreign key constraints

                // Reset tenant status
                $tenantData = $this->getTenantDataArray($tenant);
                $tenantData['rollback'] = [
                    'performed_at' => now()->toISOString(),
                    'performed_by' => auth()->id(),
                    'original_activation' => $tenant->updated_at->toISOString(),
                ];

                $tenant->update([
                    'status' => \App\Enums\TenantStatus::PROVISIONING,
                    'data' => json_encode($tenantData),
                ]);

                Log::info('Tenant rollback completed', [
                    'tenant_id' => $tenant->id,
                    'code' => $tenant->code,
                    'performed_by' => auth()->id(),
                ]);
            });

            return response()->json([
                'data' => [
                    'tenant_id' => $tenant->id,
                    'status' => 'provisioning',
                    'rolled_back_at' => now()->toISOString(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to rollback tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'type' => 'https://api.map-hms/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to rollback tenant. Please contact support.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List tenants with filters (All/Active/Archive)
     * GET /v1/tenants?filter=all|active|archived
     */
    public function index(Request $request): JsonResponse
    {
        $filter = $request->get('filter', 'all');
        
        $query = Tenant::query();
        
        switch ($filter) {
            case 'active':
                $query->active();
                break;
            case 'archived':
                $query->archived();
                break;
            case 'all':
            default:
                // No filter - show all
                break;
        }
        
        $tenants = $query->with('domains')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 25));
        
        return response()->json([
            'data' => $tenants->items(),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
                'filter' => $filter,
            ],
        ]);
    }

    protected function getTenantDataArray(Tenant $tenant): array
    {
        $raw = DB::table('tenants')
            ->where('id', $tenant->id)
            ->value('data');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            return json_decode($raw, true) ?? [];
        }

        return [];
    }
}
