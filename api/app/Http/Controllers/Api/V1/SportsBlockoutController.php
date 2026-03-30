<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SportsBlockout;
use App\Models\SportsFacility;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sports Blockout Controller
 * 
 * Handles CRUD operations for sports facility blockouts.
 * Only Sports Manager can create/manage blockouts.
 */
class SportsBlockoutController extends Controller
{
    /**
     * List blockouts for a facility
     * 
     * GET /api/v1/sports/facilities/{facility}/blockouts
     */
    public function index(Request $request, SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $facility);

        $validator = Validator::make($request->all(), [
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Invalid request parameters.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $query = SportsBlockout::where('facility_id', $facility->id)
                ->with(['facility:id,name,type', 'creator:id,name'])
                ->orderBy('start_at', 'asc');

            if ($request->filled('start_date')) {
                $query->where('start_at', '>=', $request->date('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->where('end_at', '<=', $request->date('end_date'));
            }

            $blockouts = $query->get();

            return response()->json([
                'data' => $blockouts->map(function ($blockout) {
                    return [
                        'id' => $blockout->id,
                        'facility_id' => $blockout->facility_id,
                        'facility_name' => $blockout->facility->name,
                        'start_at' => $blockout->start_at->toISOString(),
                        'end_at' => $blockout->end_at->toISOString(),
                        'reason' => $blockout->reason,
                        'created_by' => $blockout->created_by,
                        'creator_name' => $blockout->creator->name ?? null,
                        'is_active' => $blockout->isActive(),
                        'is_future' => $blockout->isFuture(),
                        'is_past' => $blockout->isPast(),
                        'metadata' => $blockout->metadata,
                        'created_at' => $blockout->created_at->toISOString(),
                        'updated_at' => $blockout->updated_at->toISOString(),
                    ];
                }),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch blockouts', [
                'error' => $e->getMessage(),
                'facility_id' => $facility->id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to fetch blockouts. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new blockout
     * 
     * POST /api/v1/sports/facilities/{facility}/blockouts
     */
    public function store(Request $request, SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        // Only Sports Manager can create blockouts
        $user = Auth::user();
        if (!$user->hasRole('Sports Manager')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Sports Manager can create blockouts.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('view', $facility);

        $validator = Validator::make($request->all(), [
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:500'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Invalid request parameters.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $startAt = $request->date('start_at');
            $endAt = $request->date('end_at');

            // Check for overlapping blockouts (merge if same reason)
            $overlappingBlockouts = SportsBlockout::where('facility_id', $facility->id)
                ->where(function ($query) use ($startAt, $endAt) {
                    $query->where('start_at', '<', $endAt)
                          ->where('end_at', '>', $startAt);
                })
                ->get();

            if ($overlappingBlockouts->isNotEmpty()) {
                $sameReason = $overlappingBlockouts->filter(function ($blockout) use ($request) {
                    return $blockout->reason === $request->string('reason');
                });

                if ($sameReason->isNotEmpty()) {
                    // Merge overlapping blockouts with same reason
                    $minStart = min($startAt, ...$sameReason->pluck('start_at')->map(fn($dt) => $dt->timestamp)->toArray());
                    $maxEnd = max($endAt, ...$sameReason->pluck('end_at')->map(fn($dt) => $dt->timestamp)->toArray());

                    // Delete overlapping blockouts
                    $sameReason->each->delete();

                    // Create merged blockout
                    $blockout = SportsBlockout::create([
                        'facility_id' => $facility->id,
                        'start_at' => date('Y-m-d H:i:s', $minStart),
                        'end_at' => date('Y-m-d H:i:s', $maxEnd),
                        'reason' => $request->string('reason'),
                        'created_by' => $user->id,
                        'metadata' => $request->input('metadata', []),
                    ]);

                    return response()->json([
                        'data' => [
                            'id' => $blockout->id,
                            'facility_id' => $blockout->facility_id,
                            'start_at' => $blockout->start_at->toISOString(),
                            'end_at' => $blockout->end_at->toISOString(),
                            'reason' => $blockout->reason,
                            'created_by' => $blockout->created_by,
                            'merged_count' => $sameReason->count(),
                            'metadata' => $blockout->metadata,
                            'created_at' => $blockout->created_at->toISOString(),
                        ],
                        'message' => 'Blockout created and merged with overlapping blockouts',
                    ], Response::HTTP_CREATED);
                } else {
                    return response()->json([
                        'type' => 'https://map-hms.dev/errors/conflict',
                        'title' => 'Blockout Conflict',
                        'status' => Response::HTTP_CONFLICT,
                        'detail' => 'There are existing blockouts for this time period. Please remove them first or adjust the time range.',
                    ], Response::HTTP_CONFLICT);
                }
            }

            // Create new blockout
            $blockout = SportsBlockout::create([
                'facility_id' => $facility->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'reason' => $request->string('reason'),
                'created_by' => $user->id,
                'metadata' => $request->input('metadata', []),
            ]);

            Log::info('Sports blockout created', [
                'blockout_id' => $blockout->id,
                'facility_id' => $facility->id,
                'user_id' => $user->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);

            return response()->json([
                'data' => [
                    'id' => $blockout->id,
                    'facility_id' => $blockout->facility_id,
                    'start_at' => $blockout->start_at->toISOString(),
                    'end_at' => $blockout->end_at->toISOString(),
                    'reason' => $blockout->reason,
                    'created_by' => $blockout->created_by,
                    'metadata' => $blockout->metadata,
                    'created_at' => $blockout->created_at->toISOString(),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create blockout', [
                'error' => $e->getMessage(),
                'facility_id' => $facility->id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create blockout. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a blockout
     * 
     * DELETE /api/v1/sports/blockouts/{blockout}
     */
    public function destroy(SportsBlockout $blockout): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        // Only Sports Manager can delete blockouts
        $user = Auth::user();
        if (!$user->hasRole('Sports Manager')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Sports Manager can delete blockouts.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('view', $blockout->facility);

        try {
            $blockoutId = $blockout->id;
            $facilityId = $blockout->facility_id;

            $blockout->delete();

            Log::info('Sports blockout deleted', [
                'blockout_id' => $blockoutId,
                'facility_id' => $facilityId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Blockout deleted successfully',
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to delete blockout', [
                'error' => $e->getMessage(),
                'blockout_id' => $blockout->id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to delete blockout. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
