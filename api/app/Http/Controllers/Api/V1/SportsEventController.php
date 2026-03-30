<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SportsEventStatus;
use App\Enums\SportsEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\EnrollSportsEventRequest;
use App\Http\Requests\Sports\StoreSportsEventRequest;
use App\Http\Requests\Sports\UpdateEnrollmentStatusRequest;
use App\Http\Requests\Sports\UpdateSportsEventRequest;
use App\Http\Resources\SportsEventResource;
use App\Models\SportsEnrollment;
use App\Models\SportsEvent;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SportsEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', SportsEvent::class);

        $events = SportsEvent::query()
            ->withCount('enrollments')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('sport'), fn ($query) => $query->where('sport', $request->string('sport')))
            ->orderBy('scheduled_at')
            ->paginate($request->integer('per_page', 25));

        return SportsEventResource::collection($events)->response();
    }

    public function store(StoreSportsEventRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', SportsEvent::class);

        $data = $request->validated();
        $event = SportsEvent::query()->create([
            'tenant_id' => Auth::user()->tenant_id,
            'campus_id' => $data['campus_id'] ?? null,
            'hostel_id' => $data['hostel_id'] ?? null,
            'sport' => $data['sport'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'end_time' => $data['end_time'] ?? null,
            'venue' => $data['venue'] ?? null,
            'capacity' => $data['capacity'] ?? 0,
            'registration_deadline' => $data['registration_deadline'] ?? null,
            'requirements' => $data['requirements'] ?? null,
            'status' => SportsEventStatus::SCHEDULED,
            'metadata' => $data['metadata'] ?? [],
        ]);

        return SportsEventResource::make($event)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SportsEvent $sportsEvent, UpdateSportsEventRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $sportsEvent);

        if ($sportsEvent->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot modify other tenant events.');
        }

        $sportsEvent->fill($request->validated());
        $sportsEvent->save();

        return SportsEventResource::make($sportsEvent)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function enroll(SportsEvent $sportsEvent, EnrollSportsEventRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('enroll', $sportsEvent);

        if ($sportsEvent->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot enroll student for another tenant event.');
        }

        $data = $request->validated();

        $enrollment = DB::transaction(function () use ($sportsEvent, $data) {
            // Check if already enrolled
            $existing = SportsEnrollment::where([
                'tenant_id' => $sportsEvent->tenant_id,
                'sports_event_id' => $sportsEvent->id,
                'student_id' => $data['student_id'],
            ])->first();

            if ($existing) {
                abort(Response::HTTP_CONFLICT, 'Student is already enrolled in this event.');
            }

            // Determine enrollment status based on capacity
            $status = $sportsEvent->hasCapacity() 
                ? SportsEnrollmentStatus::REGISTERED 
                : SportsEnrollmentStatus::WAITLISTED;

            return SportsEnrollment::query()->create([
                'tenant_id' => $sportsEvent->tenant_id,
                'sports_event_id' => $sportsEvent->id,
                'student_id' => $data['student_id'],
                'status' => $status,
                'enrolled_at' => now(),
                'waitlist_position' => $status === SportsEnrollmentStatus::WAITLISTED 
                    ? $sportsEvent->waitlistedEnrollments()->count() + 1 
                    : null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);
        });

        return response()->json([
            'data' => [
                'id' => (string) $enrollment->id,
                'status' => $enrollment->status->value,
                'waitlist_position' => $enrollment->waitlist_position,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get waitlist for an event
     * 
     * GET /api/v1/sports/events/{event}/waitlist
     */
    public function waitlist(SportsEvent $sportsEvent): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $sportsEvent);

        try {
            $waitlistedEnrollments = $sportsEvent->waitlistedEnrollments()
                ->with(['student.user'])
                ->orderBy('waitlist_position', 'asc')
                ->get()
                ->map(function ($enrollment) {
                    return [
                        'id' => $enrollment->id,
                        'student_name' => $enrollment->student->user->name ?? 'Unknown',
                        'student_uid' => $enrollment->student->student_uid ?? null,
                        'waitlist_position' => $enrollment->waitlist_position,
                        'enrolled_at' => $enrollment->enrolled_at->toISOString(),
                        'notes' => $enrollment->notes,
                    ];
                });

            return response()->json([
                'data' => $waitlistedEnrollments,
                'count' => $waitlistedEnrollments->count(),
                'event_id' => $sportsEvent->id,
                'event_name' => $sportsEvent->name,
                'capacity' => $sportsEvent->capacity,
                'registered_count' => $sportsEvent->registeredEnrollments()->count(),
                'available_spots' => $sportsEvent->getAvailableSpots(),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch event waitlist', [
                'error' => $e->getMessage(),
                'event_id' => $sportsEvent->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to fetch waitlist. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateEnrollment(SportsEvent $sportsEvent, SportsEnrollment $enrollment, UpdateEnrollmentStatusRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $enrollment);

        if ($sportsEvent->tenant_id !== Auth::user()->tenant_id || $enrollment->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot modify enrollment from another tenant.');
        }

        if ($enrollment->sports_event_id !== $sportsEvent->id) {
            abort(Response::HTTP_BAD_REQUEST, 'Enrollment does not belong to the supplied event.');
        }

        $data = $request->validated();
        $newStatus = SportsEnrollmentStatus::from($data['status']);

        if (!$enrollment->canTransitionTo($newStatus)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, "Cannot transition from {$enrollment->status->value} to {$newStatus->value}");
        }

        $success = $enrollment->transitionTo($newStatus, $data['notes'] ?? null);

        if (!$success) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to update enrollment status');
        }

        return response()->json([
            'data' => [
                'id' => (string) $enrollment->id,
                'status' => $enrollment->status->value,
                'waitlist_position' => $enrollment->waitlist_position,
            ],
        ], Response::HTTP_ACCEPTED);
    }

    public function showEnrollment(SportsEvent $sportsEvent, SportsEnrollment $enrollment): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $enrollment);

        if ($sportsEvent->tenant_id !== Auth::user()->tenant_id || $enrollment->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot view enrollment from another tenant.');
        }

        if ($enrollment->sports_event_id !== $sportsEvent->id) {
            abort(Response::HTTP_BAD_REQUEST, 'Enrollment does not belong to the supplied event.');
        }

        return response()->json([
            'data' => [
                'id' => (string) $enrollment->id,
                'student_id' => $enrollment->student_id,
                'status' => $enrollment->status->value,
                'enrolled_at' => $enrollment->enrolled_at->toISOString(),
                'attended_at' => $enrollment->attended_at?->toISOString(),
                'waitlist_position' => $enrollment->waitlist_position,
                'notes' => $enrollment->notes,
            ],
        ]);
    }

    public function show(SportsEvent $sportsEvent): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $sportsEvent);

        $sportsEvent->load(['enrollments.student', 'registeredEnrollments', 'waitlistedEnrollments']);

        return SportsEventResource::make($sportsEvent)->response();
    }

    public function destroy(SportsEvent $sportsEvent): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('delete', $sportsEvent);

        if ($sportsEvent->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot delete other tenant events.');
        }

        $sportsEvent->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
