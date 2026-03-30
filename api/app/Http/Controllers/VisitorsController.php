<?php

namespace App\Http\Controllers;

use App\Domain\Visitors\Models\GuestVisit;
use App\Models\RoomAllocation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VisitorsController extends Controller
{
    /**
     * Create a new visitor pre-registration
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', GuestVisit::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'whom_to_meet' => ['required', 'string', 'max:160'],
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'visit_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $user = $request->user();
        $student = $user->student;

        if (!$student) {
            throw ValidationException::withMessages([
                'student' => ['Student record not found for this user'],
            ]);
        }

        // Resolve hostel_id: use student's allocated hostel if not provided
        $hostelId = $validated['hostel_id'] ?? null;
        
        if (!$hostelId) {
            $allocation = RoomAllocation::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->first();

            if (!$allocation) {
                throw ValidationException::withMessages([
                    'hostel_id' => ['No active room allocation found. Please provide hostel_id.'],
                ]);
            }

            $hostelId = $allocation->hostel_id;
        }

        // Default visit_date to today
        $visitDate = isset($validated['visit_date']) 
            ? Carbon::parse($validated['visit_date'])
            : today();

        $guestVisit = GuestVisit::create([
            'tenant_id' => $user->tenant_id,
            'hostel_id' => $hostelId,
            'student_id' => $student->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'whom_to_meet' => $validated['whom_to_meet'],
            'visit_date' => $visitDate,
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $user->id,
        ]);

        return response()->json([
            'id' => $guestVisit->id,
            'status' => $guestVisit->status,
        ], 201);
    }

    /**
     * List today's visitor pre-registrations for the authenticated student
     */
    public function mineToday(Request $request): JsonResponse
    {
        $this->authorize('mine', GuestVisit::class);

        $user = $request->user();
        $student = $user->student;

        if (!$student) {
            return response()->json([]);
        }

        $visits = GuestVisit::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_id', $student->id)
            ->whereDate('visit_date', today())
            ->select(['id', 'name', 'phone', 'whom_to_meet', 'visit_date', 'status'])
            ->get();

        return response()->json($visits);
    }

    /**
     * Cancel a visitor pre-registration
     */
    public function cancel(Request $request, GuestVisit $guestVisit): JsonResponse
    {
        $this->authorize('cancel', $guestVisit);

        // Update to denied status (soft cancel)
        $guestVisit->update([
            'status' => GuestVisit::STATUS_DENIED,
            'denied_by_user_id' => $request->user()->id,
            'denied_at' => now(),
        ]);

        return response()->json([
            'message' => 'Visitor pre-registration cancelled',
        ]);
    }
}

