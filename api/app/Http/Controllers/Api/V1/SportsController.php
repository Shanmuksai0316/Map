<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SportsEventResource;
use App\Models\SportsEvent;
use App\Models\SportsEnrollment;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', SportsEvent::class);

        $events = SportsEvent::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->filled('from'), fn ($query) => $query->where('scheduled_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('scheduled_at', '<=', $request->date('to')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with(['enrollments.student.user'])
            ->orderBy('scheduled_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => SportsEventResource::collection($events)
        ]);
    }

    public function show(SportsEvent $event): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $event);

        return response()->json([
            'data' => SportsEventResource::make($event->load(['enrollments.student.user']))
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', SportsEvent::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:30|max:480',
            'capacity' => 'required|integer|min:1|max:100',
            'location' => 'required|string|max:255',
            'equipment_required' => 'nullable|array',
            'equipment_required.*' => 'string|max:255',
        ]);

        $event = SportsEvent::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'],
            'capacity' => $validated['capacity'],
            'location' => $validated['location'],
            'equipment_required' => $validated['equipment_required'] ?? [],
            'status' => 'scheduled',
        ]);

        return response()->json([
            'data' => SportsEventResource::make($event)
        ], Response::HTTP_CREATED);
    }

    public function enroll(Request $request, SportsEvent $event): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('enroll', $event);

        $user = Auth::user();
        $studentId = $user->student?->id;

        if (!$studentId) {
            return response()->json([
                'error' => 'Only students can enroll in sports events'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check capacity
        $currentEnrollments = $event->enrollments()->count();
        if ($currentEnrollments >= $event->capacity) {
            return response()->json([
                'error' => 'Event is at full capacity'
            ], Response::HTTP_CONFLICT);
        }

        // Check if already enrolled
        $existingEnrollment = $event->enrollments()
            ->where('student_id', $studentId)
            ->first();

        if ($existingEnrollment) {
            return response()->json([
                'error' => 'Already enrolled in this event'
            ], Response::HTTP_CONFLICT);
        }

        $enrollment = SportsEnrollment::create([
            'tenant_id' => $user->tenant_id,
            'sports_event_id' => $event->id,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'event_id' => $event->id,
                'status' => 'enrolled',
            ]
        ], Response::HTTP_CREATED);
    }

    public function unenroll(SportsEnrollment $enrollment): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('unenroll', $enrollment);

        $enrollment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'status' => 'cancelled',
            ]
        ]);
    }

    public function cancel(SportsEvent $event): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('cancel', $event);

        $event->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        // Cancel all enrollments
        $event->enrollments()->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'data' => SportsEventResource::make($event)
        ]);
    }
}
