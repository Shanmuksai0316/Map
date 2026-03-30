<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LaundryRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Laundry\StoreLaundryRequestRequest;
use App\Http\Requests\Laundry\UpdateLaundryRequestRequest;
use App\Http\Requests\Laundry\UpdateLaundryRequestStatusRequest;
use App\Http\Resources\LaundryRequestResource;
use App\Models\LaundryRequest;
use App\Services\Notify\PushNotifier;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LaundryRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', LaundryRequest::class);

        $requests = LaundryRequest::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('service_type'), fn ($query) => $query->where('service_type', $request->string('service_type')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('overdue'), fn ($query) => $query->whereRaw('estimated_completion_at < NOW()'))
            ->with(['student.user', 'cycle', 'hostel'])
            ->latest('created_at')
            ->paginate($request->integer('per_page', 25));

        return LaundryRequestResource::collection($requests)->response();
    }

    public function store(StoreLaundryRequestRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', LaundryRequest::class);

        $laundryRequest = DB::transaction(function () use ($request) {
            $laundryRequest = LaundryRequest::query()->create([
                'tenant_id' => Auth::user()->tenant_id,
                'campus_id' => Auth::user()->campus_id,
                'hostel_id' => $request->integer('hostel_id') ?: Auth::user()->hostel_id,
                'student_id' => $request->integer('student_id'),
                'service_type' => $request->input('service_type'),
                'status' => LaundryRequestStatus::PENDING,
                'bag_count' => $request->integer('bag_count', 1),
                'special_instructions' => $request->string('special_instructions'),
                'requested_at' => now(),
                'metadata' => $request->input('metadata', []),
            ]);

            // Calculate estimated completion time
            $estimatedCompletion = $laundryRequest->calculateEstimatedCompletion();
            $laundryRequest->update(['estimated_completion_at' => $estimatedCompletion]);

            return $laundryRequest;
        });

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $laundryRequest);

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function update(UpdateLaundryRequestRequest $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $laundryRequest);

        $laundryRequest->update($request->validated());

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function destroy(LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('delete', $laundryRequest);

        $laundryRequest->delete();

        return response()->json(['message' => 'Laundry request deleted successfully'], Response::HTTP_OK);
    }

    public function updateStatus(UpdateLaundryRequestStatusRequest $request, LaundryRequest $laundryRequest, PushNotifier $push): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('updateStatus', $laundryRequest);

        $newStatus = LaundryRequestStatus::from($request->string('status')->toString());

        if (!$laundryRequest->canTransitionTo($newStatus)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Invalid Status Transition',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => "Cannot transition from {$laundryRequest->status->value} to {$newStatus->value}",
                'current_status' => $laundryRequest->status->value,
                'requested_status' => $newStatus->value,
                'allowed_transitions' => array_map(
                    fn($status) => $status->value,
                    array_filter(
                        LaundryRequestStatus::cases(),
                        fn($status) => $laundryRequest->status->canTransitionTo($status)
                    )
                ),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $success = $laundryRequest->transitionTo($newStatus, $request->string('notes'));

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/status_update_failed',
                'title' => 'Status Update Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to update laundry request status',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Notify student about laundry status change
        try {
            $student = $laundryRequest->student()->with('user')->first();
            $user    = $student?->user;

            if ($user) {
                $statusMessage = sprintf(
                    'Your laundry request #%d status changed to %s.',
                    $laundryRequest->id,
                    $newStatus->value
                );

                $push->toUserTemplate(
                    $user->id,
                    'student.laundry_status',
                    [
                        'status_message' => $statusMessage,
                    ],
                    [
                        'type'              => 'laundry_status',
                        'laundry_request_id'=> $laundryRequest->id,
                        'status'            => $newStatus->value,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Soft-fail push; main API should still succeed
        }

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function collect(Request $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('collect', $laundryRequest);

        $request->validate([
            'collection_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $success = $laundryRequest->transitionTo(
            LaundryRequestStatus::COLLECTED,
            $request->string('collection_notes')
        );

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Cannot Collect Request',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Request must be in scheduled status to be collected',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function deliver(Request $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('deliver', $laundryRequest);

        $request->validate([
            'delivery_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $success = $laundryRequest->transitionTo(
            LaundryRequestStatus::DELIVERED,
            $request->string('delivery_notes')
        );

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Cannot Deliver Request',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Request must be in ready status to be delivered',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function cancel(Request $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('cancel', $laundryRequest);

        $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $success = $laundryRequest->transitionTo(
            LaundryRequestStatus::CANCELLED,
            $request->string('cancellation_reason')
        );

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Cannot Cancel Request',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Request cannot be cancelled in its current status',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function markAsLost(Request $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('markAsLost', $laundryRequest);

        $request->validate([
            'lost_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $success = $laundryRequest->transitionTo(
            LaundryRequestStatus::LOST,
            $request->string('lost_reason')
        );

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Cannot Mark as Lost',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Request must be active to be marked as lost',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function markAsDamaged(Request $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('markAsDamaged', $laundryRequest);

        $request->validate([
            'damage_description' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $success = $laundryRequest->transitionTo(
            LaundryRequestStatus::DAMAGED,
            $request->string('damage_description')
        );

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Cannot Mark as Damaged',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Request must be active to be marked as damaged',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function manualVerify(Request $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $laundryRequest);

        $request->validate([
            'verify_notes' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (!$laundryRequest->requiresManualVerify()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_state',
                'title' => 'Manual Verification Not Required',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Request must be in ready status for manual verification',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $success = $laundryRequest->manualVerify($request->string('verify_notes'));

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/verification_failed',
                'title' => 'Manual Verification Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to perform manual verification',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    /**
     * Raise a laundry request on behalf of a student (Laundry Manager only)
     */
    public function raiseForStudent(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'room_number' => ['nullable', 'string', 'max:20'],
            'item_count' => ['required', 'integer', 'min:1', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $student = \App\Models\Student::with('user', 'hostel')->findOrFail($validated['student_id']);

        $laundryRequest = DB::transaction(function () use ($request, $validated, $student) {
            $laundryRequest = LaundryRequest::query()->create([
                'tenant_id' => Auth::user()->tenant_id,
                'campus_id' => Auth::user()->campus_id,
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'service_type' => 'standard',
                'status' => LaundryRequestStatus::PENDING,
                'bag_count' => 1,
                'item_count' => $validated['item_count'],
                'weight_kg' => $validated['weight_kg'] ?? null,
                'room_number' => $validated['room_number'] ?? $student->room?->number,
                'special_instructions' => $validated['special_instructions'] ?? $validated['description'] ?? null,
                'requested_at' => now(),
                'initiated_by' => Auth::user()->id,
                'metadata' => [
                    'raised_by_manager' => true,
                    'manager_id' => Auth::user()->id,
                    'manager_name' => Auth::user()->name,
                ],
            ]);

            // Calculate estimated completion time
            $estimatedCompletion = $laundryRequest->calculateEstimatedCompletion();
            $laundryRequest->update(['estimated_completion_at' => $estimatedCompletion]);

            return $laundryRequest;
        });

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Mark a laundry request as ready for pickup (Laundry Manager only)
     */
    public function markReadyForPickup(Request $request, LaundryRequest $laundryRequest): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        if ($laundryRequest->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'error' => 'Unauthorized',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Transition to READY status
        $success = $laundryRequest->transitionTo(
            LaundryRequestStatus::READY,
            $request->string('notes', 'Marked ready for pickup by laundry manager')
        );

        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Cannot Mark as Ready',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Request cannot be marked as ready in its current status',
                'current_status' => $laundryRequest->status->value,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // TODO: Send notification to student that laundry is ready

        return LaundryRequestResource::make($laundryRequest->load(['student.user', 'cycle', 'hostel']))->response();
    }

    public function metrics(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewMetrics', LaundryRequest::class);

        $tenantId = Auth::user()->tenant_id;

        $metrics = [
            'total_requests' => LaundryRequest::query()->where('tenant_id', $tenantId)->count(),
            'active_requests' => LaundryRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', LaundryRequestStatus::getActiveStatuses())
                ->count(),
            'completed_requests' => LaundryRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', LaundryRequestStatus::getCompletedStatuses())
                ->count(),
            'overdue_requests' => LaundryRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', LaundryRequestStatus::getActiveStatuses())
                ->whereRaw('estimated_completion_at < NOW()')
                ->count(),
            'requests_by_status' => LaundryRequest::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'requests_by_service_type' => LaundryRequest::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw('service_type, COUNT(*) as count')
                ->groupBy('service_type')
                ->pluck('count', 'service_type'),
            'average_completion_time' => LaundryRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('completed_at')
                ->whereNotNull('requested_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - requested_at))/3600) as avg_hours')
                ->value('avg_hours'),
        ];

        return response()->json(['data' => $metrics], Response::HTTP_OK);
    }
}
