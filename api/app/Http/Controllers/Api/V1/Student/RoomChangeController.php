<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notify\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoomChangeController extends Controller
{
    /**
     * Get student's own room change requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $roomChanges = RoomChange::where('student_id', $user->student->id)
                ->latest('submitted_at')
                ->get()
                ->map(function ($roomChange) {
                    return [
                        'id' => (string) $roomChange->id,
                        'unique_id' => $roomChange->unique_id,
                        'title' => $roomChange->title,
                        'description' => $roomChange->description,
                        'preferred_room_number' => $roomChange->preferred_room_number,
                        'preferred_floor' => $roomChange->preferred_floor,
                        'sharing_preference' => $roomChange->sharing_preference,
                        'date_required' => $roomChange->date_required ? $roomChange->date_required->format('Y-m-d') : null,
                        'status' => $roomChange->status,
                        'rejection_reason' => $roomChange->rejection_reason,
                        'submitted_date' => $roomChange->submitted_at->format('Y-m-d H:i:s'),
                        'created_at' => $roomChange->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'data' => $roomChanges,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student room changes', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve room change requests. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create new room change request
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can create room change requests.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'preferred_room_number' => 'nullable|string|max:50',
            'preferred_floor' => 'nullable|string|max:50',
            'sharing_preference' => 'nullable|in:single,double,triple,quad',
            'date_required' => 'nullable|date',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $idempotencyKey = $validated['idempotency_key'] ?? Str::uuid()->toString();
            
            // Check for duplicate
            $existingRoomChange = RoomChange::where('idempotency_key', $idempotencyKey)->first();
            if ($existingRoomChange) {
                return response()->json([
                    'data' => [
                        'id' => (string) $existingRoomChange->id,
                        'unique_id' => $existingRoomChange->unique_id,
                        'message' => 'Room change request already created',
                    ],
                ], Response::HTTP_OK);
            }

            $roomChange = RoomChange::create([
                'student_id' => $user->student->id,
                'hostel_id' => $user->student->hostel_id ?? null,
                'title' => 'Room Change Request',
                'description' => $validated['description'],
                'preferred_room_number' => $validated['preferred_room_number'] ?? null,
                'preferred_floor' => $validated['preferred_floor'] ?? null,
                'sharing_preference' => $validated['sharing_preference'] ?? null,
                'date_required' => $validated['date_required'] ?? null,
                'status' => 'pending',
                'submitted_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->notifyApprovers($user, $roomChange);

            return response()->json([
                'message' => 'Room change request created successfully',
                'data' => [
                    'id' => (string) $roomChange->id,
                    'unique_id' => $roomChange->unique_id,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create room change request', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/create_failed',
                'title' => 'Create Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create room change request. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function notifyApprovers($user, RoomChange $roomChange): void
    {
        try {
            $tenantId = (string) ($user->tenant_id ?? '');
            $hostelId = (int) ($roomChange->hostel_id ?? 0);
            if ($tenantId === '' || $hostelId <= 0) {
                return;
            }

            $push = app(PushNotifier::class);
            $recipients = app(NotificationRecipients::class);

            $studentName = (string) ($user->name ?? 'Student');
            $requestId = (string) ($roomChange->unique_id ?? $roomChange->id);
            $title = 'New room change request';
            $body = "Student {$studentName} submitted room change request #{$requestId}.";
            $data = [
                'type' => 'room_change_submitted',
                'room_change_id' => (string) $roomChange->id,
            ];

            $notified = [];

            $rector = $recipients->rectorForHostel($tenantId, $hostelId);
            if ($rector && !in_array($rector->id, $notified, true)) {
                $push->toUser($rector->id, $title, $body, $data);
                $notified[] = $rector->id;
            }

            $campusManager = $recipients->campusManagerForTenant($tenantId);
            if ($campusManager && !in_array($campusManager->id, $notified, true)) {
                $push->toUser($campusManager->id, $title, $body, $data);
            }
        } catch (\Throwable $e) {
            Log::warning('room_change.notify_approvers_failed', [
                'room_change_id' => $roomChange->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get specific room change details
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $roomChange = RoomChange::where('id', $id)
                ->where('student_id', $user->student->id)
                ->firstOrFail();

            return response()->json([
                'data' => [
                    'id' => (string) $roomChange->id,
                    'unique_id' => $roomChange->unique_id,
                    'title' => $roomChange->title,
                    'description' => $roomChange->description,
                    'preferred_room_number' => $roomChange->preferred_room_number,
                    'preferred_floor' => $roomChange->preferred_floor,
                    'sharing_preference' => $roomChange->sharing_preference,
                    'date_required' => $roomChange->date_required ? $roomChange->date_required->format('Y-m-d') : null,
                    'status' => $roomChange->status,
                    'rejection_reason' => $roomChange->rejection_reason,
                    'submitted_date' => $roomChange->submitted_at->format('Y-m-d H:i:s'),
                    'created_at' => $roomChange->created_at->toIso8601String(),
                ],
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'Room change request not found.',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to fetch room change details', [
                'error' => $e->getMessage(),
                'room_change_id' => $id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve room change details. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
