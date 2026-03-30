<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OfflineAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OfflineSyncController extends Controller
{
    /**
     * Sync offline actions.
     */
    public function sync(Request $request): JsonResponse
    {
        // Accept both mobile schema (id, payload, client_timestamp) and API schema (action_id, action_data)
        $validated = $request->validate([
            'actions' => 'required|array',
            'actions.*.action_type' => 'required|string',
            // Accept mobile schema fields
            'actions.*.id' => 'sometimes|string',
            'actions.*.payload' => 'sometimes|array',
            'actions.*.client_timestamp' => 'sometimes|string',
            // Accept API schema fields (for backward compatibility)
            'actions.*.action_id' => 'sometimes|string',
            'actions.*.action_data' => 'sometimes|array',
            'actions.*.device_id' => 'sometimes|string',
            'actions.*.metadata' => 'sometimes|array',
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

        try {
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($validated['actions'] as $actionData) {
                try {
                    // Map mobile schema to API schema
                    // Mobile sends: { id, action_type, payload, client_timestamp }
                    // API expects: { action_id, action_type, action_data, device_id, metadata }
                    $actionId = $actionData['action_id'] ?? $actionData['id'] ?? null;
                    $actionType = $actionData['action_type'];
                    $actionDataPayload = $actionData['action_data'] ?? $actionData['payload'] ?? [];
                    $deviceId = $actionData['device_id'] ?? $actionData['metadata']['device_id'] ?? null;
                    $metadata = $actionData['metadata'] ?? [];
                    
                    // Add client_timestamp to metadata if provided
                    if (isset($actionData['client_timestamp'])) {
                        $metadata['client_timestamp'] = $actionData['client_timestamp'];
                    }

                    if (!$actionId) {
                        throw new \Exception('action_id or id is required');
                    }

                    // Check if action already exists
                    $existingAction = OfflineAction::where('action_id', $actionId)
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $user->id)
                        ->first();

                    if ($existingAction) {
                        $results[] = [
                            'action_id' => $actionId,
                            'status' => 'duplicate',
                            'message' => 'Action already exists',
                        ];
                        continue;
                    }

                    // Create new offline action
                    $action = OfflineAction::createAction(
                        $tenantId,
                        $user->id,
                        $actionType,
                        $actionDataPayload,
                        $deviceId,
                        $metadata
                    );

                    $results[] = [
                        'action_id' => $action->action_id,
                        'status' => 'queued',
                        'message' => 'Action queued for processing',
                    ];

                    $successCount++;

                } catch (\Exception $e) {
                    $actionId = $actionData['action_id'] ?? $actionData['id'] ?? 'unknown';
                    $results[] = [
                        'action_id' => $actionId,
                        'status' => 'failed',
                        'message' => $e->getMessage(),
                    ];
                    $failureCount++;
                }
            }

            Log::info('Offline actions synced', [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'total_actions' => count($validated['actions']),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);

            return response()->json([
                'message' => 'Offline actions synced successfully',
                'data' => [
                    'total_actions' => count($validated['actions']),
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'results' => $results,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to sync offline actions', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/offline_sync_failed',
                'title' => 'Offline Sync Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to sync offline actions. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get offline queue status.
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        $user = $request->user();
        $tenantId = tenancy()->tenant?->id;
        $days = $validated['days'] ?? 30;

        if (!$tenantId) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'This action requires a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $stats = OfflineAction::getActionStats($tenantId, $days);
            $pendingActions = OfflineAction::getPendingActions($tenantId, 10);
            $retryableActions = OfflineAction::getRetryableActions($tenantId);

            return response()->json([
                'data' => [
                    'stats' => $stats,
                    'pending_actions' => $pendingActions->map(function ($action) {
                        return [
                            'action_id' => $action->action_id,
                            'action_type' => $action->action_type,
                            'queued_at' => $action->queued_at,
                            'retry_count' => $action->retry_count,
                        ];
                    }),
                    'retryable_actions' => $retryableActions->map(function ($action) {
                        return [
                            'action_id' => $action->action_id,
                            'action_type' => $action->action_type,
                            'failed_at' => $action->failed_at,
                            'retry_count' => $action->retry_count,
                            'error_message' => $action->error_message,
                        ];
                    }),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to get offline queue status', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/status_fetch_failed',
                'title' => 'Status Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve offline queue status.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Process pending offline actions.
     */
    public function process(Request $request): JsonResponse
    {
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

        // Check if user has permission to process offline actions
        if (!$this->canProcessOfflineActions($user)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/insufficient_permissions',
                'title' => 'Insufficient Permissions',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'You do not have permission to process offline actions.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $pendingActions = OfflineAction::getPendingActions($tenantId, 50);
            $processedCount = 0;
            $failedCount = 0;

            foreach ($pendingActions as $action) {
                try {
                    $action->markAsProcessing();
                    
                    // Process the action based on its type
                    $this->processAction($action);
                    
                    $action->markAsCompleted();
                    $processedCount++;

                } catch (\Exception $e) {
                    $action->markAsFailed($e->getMessage());
                    $failedCount++;
                    
                    Log::error('Failed to process offline action', [
                        'action_id' => $action->action_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Offline actions processed', [
                'tenant_id' => $tenantId,
                'processed_by' => $user->id,
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
            ]);

            return response()->json([
                'message' => 'Offline actions processed successfully',
                'data' => [
                    'processed_count' => $processedCount,
                    'failed_count' => $failedCount,
                    'total_processed' => $processedCount + $failedCount,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to process offline actions', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/processing_failed',
                'title' => 'Processing Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to process offline actions.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Process a specific offline action.
     */
    private function processAction(OfflineAction $action): void
    {
        switch ($action->action_type) {
            case 'gate_entry':
                $this->processGateEntry($action);
                break;
            case 'gate_exit':
                $this->processGateExit($action);
                break;
            case 'attendance_update':
                $this->processAttendanceUpdate($action);
                break;
            case 'visitor_log':
                $this->processVisitorLog($action);
                break;
            default:
                throw new \Exception("Unknown action type: {$action->action_type}");
        }
    }

    /**
     * Process gate entry action.
     */
    private function processGateEntry(OfflineAction $action): void
    {
        $data = $action->action_data;
        
        // Implement gate entry processing logic
        // This would typically update attendance records, send notifications, etc.
        
        Log::info('Gate entry processed', [
            'action_id' => $action->action_id,
            'student_id' => $data['student_id'] ?? null,
        ]);
    }

    /**
     * Process gate exit action.
     */
    private function processGateExit(OfflineAction $action): void
    {
        $data = $action->action_data;
        
        // Implement gate exit processing logic
        
        Log::info('Gate exit processed', [
            'action_id' => $action->action_id,
            'student_id' => $data['student_id'] ?? null,
        ]);
    }

    /**
     * Process attendance update action.
     */
    private function processAttendanceUpdate(OfflineAction $action): void
    {
        $data = $action->action_data;
        
        // Implement attendance update processing logic
        
        Log::info('Attendance update processed', [
            'action_id' => $action->action_id,
            'student_id' => $data['student_id'] ?? null,
        ]);
    }

    /**
     * Process visitor log action.
     */
    private function processVisitorLog(OfflineAction $action): void
    {
        $data = $action->action_data;
        
        // Implement visitor log processing logic
        
        Log::info('Visitor log processed', [
            'action_id' => $action->action_id,
            'visitor_name' => $data['visitor_name'] ?? null,
        ]);
    }

    /**
     * Check if user can process offline actions.
     */
    private function canProcessOfflineActions($user): bool
    {
        // Only staff members can process offline actions
        return in_array($user->role, ['campus_manager', 'rector', 'warden', 'guard']);
    }
}
