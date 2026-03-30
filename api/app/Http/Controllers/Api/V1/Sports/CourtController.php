<?php

namespace App\Http\Controllers\Api\V1\Sports;

use App\Http\Controllers\Controller;
use App\Models\SportsCourt;
use App\Models\FacilityBooking;
use App\Models\SportsFacility;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Court Controller for Sports Manager
 * 
 * Manages sports courts/facilities CRUD and active requests
 */
class CourtController extends Controller
{
    /**
     * Get all courts/facilities
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = DB::table('sports_facilities')
            ->where('tenant_id', $user->tenant_id);

        // Filter by category/type
        if ($request->filled('category')) {
            $query->where('type', $request->string('category'));
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $courts = $query->orderBy('name')
            ->get()
            ->map(fn ($court) => [
                'id' => $court->id,
                'name' => $court->name,
                'category' => $court->type,
                'location' => $court->location,
                'capacity' => $court->capacity,
                'is_active' => (bool) $court->is_active,
                'description' => $court->description,
                'created_at' => $court->created_at,
            ]);

        return response()->json(['data' => $courts]);
    }

    /**
     * Create a new court/facility
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'location' => 'nullable|string|max:200',
            'capacity' => 'nullable|integer|min:1|max:1000',
            'description' => 'nullable|string|max:500',
        ]);

        $court = DB::table('sports_facilities')->insertGetId([
            'tenant_id' => $user->tenant_id,
            'name' => $validated['name'],
            'type' => $validated['category'],
            'location' => $validated['location'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Court created successfully',
            'data' => [
                'id' => $court,
                'name' => $validated['name'],
                'category' => $validated['category'],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get single court details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $court = DB::table('sports_facilities')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$court) {
            return response()->json([
                'error' => 'Court not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Get today's bookings for this court
        $bookings = FacilityBooking::query()
            ->where('facility_id', $id)
            ->whereDate('booking_date', today())
            ->with(['user'])
            ->get()
            ->map(fn ($booking) => [
                'id' => $booking->id,
                'user_name' => $booking->user?->name,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'status' => $booking->status,
            ]);

        return response()->json([
            'data' => [
                'id' => $court->id,
                'name' => $court->name,
                'category' => $court->type,
                'location' => $court->location,
                'capacity' => $court->capacity,
                'description' => $court->description,
                'is_active' => (bool) $court->is_active,
                'created_at' => $court->created_at,
                'today_bookings' => $bookings,
            ],
        ]);
    }

    /**
     * Update a court/facility
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'category' => 'sometimes|string|max:50',
            'location' => 'nullable|string|max:200',
            'capacity' => 'nullable|integer|min:1|max:1000',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $court = DB::table('sports_facilities')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$court) {
            return response()->json([
                'error' => 'Court not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $updateData = [
            'updated_at' => now(),
        ];

        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['category'])) {
            $updateData['type'] = $validated['category'];
        }
        if (array_key_exists('location', $validated)) {
            $updateData['location'] = $validated['location'];
        }
        if (array_key_exists('capacity', $validated)) {
            $updateData['capacity'] = $validated['capacity'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (isset($validated['is_active'])) {
            $updateData['is_active'] = $validated['is_active'];
        }

        DB::table('sports_facilities')
            ->where('id', $id)
            ->update($updateData);

        return response()->json([
            'message' => 'Court updated successfully',
            'data' => [
                'id' => $id,
            ],
        ]);
    }

    /**
     * Delete a court/facility
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $court = DB::table('sports_facilities')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$court) {
            return response()->json([
                'error' => 'Court not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if there are any active bookings
        $activeBookings = FacilityBooking::query()
            ->where('facility_id', $id)
            ->whereDate('booking_date', '>=', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();

        if ($activeBookings > 0) {
            return response()->json([
                'error' => 'Cannot delete court with active bookings',
                'active_bookings' => $activeBookings,
            ], Response::HTTP_CONFLICT);
        }

        DB::table('sports_facilities')
            ->where('id', $id)
            ->delete();

        return response()->json([
            'message' => 'Court deleted successfully',
        ]);
    }

    /**
     * Get all active booking requests
     */
    public function activeRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = FacilityBooking::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereDate('booking_date', '>=', today())
            ->with(['facility', 'user']);

        // Filter by sport/facility type
        if ($request->filled('sport')) {
            $query->whereHas('facility', fn ($q) => $q->where('type', $request->string('sport')));
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('booking_date', $request->date('date'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $bookings = $query->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $bookings->map(fn ($booking) => [
                'id' => $booking->id,
                'user_name' => $booking->user?->name,
                'facility' => [
                    'id' => $booking->facility?->id,
                    'name' => $booking->facility?->name,
                    'type' => $booking->facility?->type,
                ],
                'booking_date' => $booking->booking_date,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'status' => $booking->status,
                'purpose' => $booking->purpose,
                'created_at' => $booking->created_at,
            ]),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Toggle court active status
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $court = DB::table('sports_facilities')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$court) {
            return response()->json([
                'error' => 'Court not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $newStatus = !$court->is_active;

        DB::table('sports_facilities')
            ->where('id', $id)
            ->update([
                'is_active' => $newStatus,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Court status updated',
            'data' => [
                'id' => $id,
                'is_active' => $newStatus,
            ],
        ]);
    }

    /**
     * Sports Manager: raise a booking on behalf of a student (by name).
     * POST /sports/raise-booking
     */
    public function raiseBooking(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant required'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'facility_id' => 'required|integer',
            'date' => 'required|date|after_or_equal:today',
            'slot_start' => 'required|string|regex:/^\d{1,2}:\d{2}$/', // e.g. 09:00
            'duration_minutes' => 'integer|min:30|max:240',
            'student_name' => 'required|string|max:255',
        ]);

        $duration = $validated['duration_minutes'] ?? 60;
        $date = Carbon::parse($validated['date']);
        $startAt = Carbon::parse($validated['date'] . ' ' . $validated['slot_start']);
        $endAt = (clone $startAt)->addMinutes($duration);

        if ($endAt->isPast()) {
            return response()->json(['error' => 'Selected slot is in the past'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $facility = SportsFacility::where('id', $validated['facility_id'])->first();
        if (!$facility || ($facility->tenant_id && $facility->tenant_id !== $tenantId)) {
            return response()->json(['error' => 'Court not found'], Response::HTTP_NOT_FOUND);
        }

        $student = Student::with('user')->whereHas('user', function ($q) use ($validated) {
            $q->where('name', 'ilike', '%' . trim($validated['student_name']) . '%');
        })->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found with that name'], Response::HTTP_NOT_FOUND);
        }

        if (!$facility->isAvailable($startAt, $endAt)) {
            return response()->json(['error' => 'Court is not available for the selected slot'], Response::HTTP_CONFLICT);
        }

        $booking = FacilityBooking::create([
            'facility_id' => $facility->id,
            'student_id' => $student->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'active',
            'purpose' => 'Raised by Sports Manager',
            'participants' => 1,
            'notes' => null,
        ]);

        return response()->json([
            'message' => 'Booking raised successfully. Student will be notified.',
            'data' => [
                'id' => $booking->id,
                'facility_name' => $facility->name,
                'student_name' => $student->user->name ?? $validated['student_name'],
                'start_at' => $booking->start_at->toIso8601String(),
                'end_at' => $booking->end_at->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }
}

