<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OfflineQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Offline Queue Controller
 * 
 * Handles synchronization of offline actions from mobile apps
 */
class OfflineController extends Controller
{
    protected OfflineQueueService $offlineQueueService;

    public function __construct(OfflineQueueService $offlineQueueService)
    {
        $this->offlineQueueService = $offlineQueueService;
    }

    /**
     * Sync offline actions
     * 
     * POST /api/v1/offline/sync
     * 
     * Request body:
     * {
     *   "actions": [
     *     {
     *       "action_type": "gate_out",
     *       "client_timestamp": "2025-10-29T10:30:00Z",
     *       "payload": {
     *         "student_id": 123,
     *         "outpass_id": 456,
     *         "hostel_id": 1
     *       }
     *     }
     *   ]
     * }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validate request
        $validated = $request->validate([
            'actions' => 'required|array|min:1|max:100',
            'actions.*.action_type' => 'required|string|in:gate_out,gate_in,attendance_mark,emergency_exit',
            'actions.*.client_timestamp' => 'nullable|date',
            'actions.*.payload' => 'required|array',
        ]);

        try {
            $results = $this->offlineQueueService->processBatch(
                $validated['actions'],
                $user
            );

            // Determine response status
            $httpStatus = Response::HTTP_OK;
            if ($results['failed'] > 0 && $results['succeeded'] === 0) {
                $httpStatus = Response::HTTP_PARTIAL_CONTENT;
            }

            return response()->json([
                'message' => 'Offline actions processed',
                'data' => [
                    'processed' => $results['processed'],
                    'succeeded' => $results['succeeded'],
                    'failed' => $results['failed'],
                    'errors' => $results['errors'],
                ],
            ], $httpStatus);

        } catch (\Exception $e) {
            Log::error('Offline sync failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'actions_count' => count($validated['actions']),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/offline_sync_failed',
                'title' => 'Offline Sync Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to process offline actions. Please try again.',
                'errors' => [$e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get offline action history for current user
     * 
     * GET /api/v1/offline/history
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 50);

        try {
            $actions = $this->offlineQueueService->getUserOfflineActions($user, $limit);

            return response()->json([
                'data' => $actions->map(function ($action) {
                    return [
                        'id' => $action->id,
                        'action_type' => $action->action_type,
                        'client_timestamp' => $action->client_timestamp?->toIso8601String(),
                        'processed_at' => $action->processed_at?->toIso8601String(),
                        'status' => $action->status,
                        'created_at' => $action->created_at?->toIso8601String(),
                    ];
                }),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch offline history', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/history_fetch_failed',
                'title' => 'History Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve offline action history.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Health check endpoint for offline sync service
     * 
     * GET /api/v1/offline/health
     * 
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'offline-queue',
            'timestamp' => now()->toIso8601String(),
        ], Response::HTTP_OK);
    }
}

