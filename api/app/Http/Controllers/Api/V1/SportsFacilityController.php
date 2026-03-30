<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SportsFacilityResource;
use App\Models\SportsFacility;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SportsFacilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', SportsFacility::class);

        $facilities = SportsFacility::query()
            ->where('is_active', true)
            ->when($request->filled('hostel_id'), fn ($query) => $query->where('hostel_id', $request->integer('hostel_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->with(['hostel'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => SportsFacilityResource::collection($facilities)
        ]);
    }

    public function show(SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $facility);

        return response()->json([
            'data' => SportsFacilityResource::make($facility->load(['hostel']))
        ]);
    }

    public function availability(Request $request, SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $facility);

        $request->validate([
            'date' => 'required|date',
            'duration' => 'integer|min:30|max:480|default:60',
        ]);

        $date = $request->date('date');
        $duration = $request->integer('duration', 60);

        $availableSlots = $facility->getAvailableSlots($date, $duration);

        return response()->json([
            'data' => [
                'facility' => SportsFacilityResource::make($facility),
                'date' => $date->format('Y-m-d'),
                'duration_minutes' => $duration,
                'available_slots' => $availableSlots,
                'total_slots' => count($availableSlots),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', SportsFacility::class);

        $validated = $request->validate([
            'hostel_id' => 'required|integer|exists:hostels,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'open_time' => 'required|date_format:H:i',
            'close_time' => 'required|date_format:H:i|after:open_time',
            'capacity' => 'required|integer|min:1|max:500',
            'description' => 'nullable|string|max:1000',
            'rules' => 'nullable|array',
            'is_active' => 'boolean|default:true',
        ]);

        $facility = SportsFacility::create($validated);

        return response()->json([
            'data' => SportsFacilityResource::make($facility->load(['hostel']))
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $facility);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'type' => 'string|max:50',
            'open_time' => 'date_format:H:i',
            'close_time' => 'date_format:H:i|after:open_time',
            'capacity' => 'integer|min:1|max:500',
            'description' => 'nullable|string|max:1000',
            'rules' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $facility->fill($validated);
        $facility->save();

        return response()->json([
            'data' => SportsFacilityResource::make($facility->load(['hostel']))
        ]);
    }

    public function destroy(SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('delete', $facility);

        // Check if facility has active bookings
        if ($facility->activeBookings()->exists()) {
            return response()->json([
                'error' => 'Cannot delete facility with active bookings'
            ], Response::HTTP_CONFLICT);
        }

        $facility->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get facility occupancy and monitoring data
     * 
     * GET /api/v1/sports/facilities/{facility}/occupancy
     */
    public function occupancy(Request $request, SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $facility);

        try {
            $now = now();
            
            // Get current bookings
            $currentBookings = $facility->activeBookings()
                ->where('start_at', '<=', $now)
                ->where('end_at', '>=', $now)
                ->with(['student.user'])
                ->get();

            // Get upcoming bookings
            $upcomingBookings = $facility->upcomingBookings()
                ->with(['student.user'])
                ->limit(10)
                ->get();

            // Get total active bookings count
            $totalActiveBookings = $facility->activeBookings()->count();

            // Calculate occupancy percentage
            $occupancyPercentage = $facility->capacity > 0 
                ? min(100, ($totalActiveBookings / $facility->capacity) * 100) 
                : 0;

            // Get blockouts
            $activeBlockouts = $facility->blockouts()
                ->where('start_at', '<=', $now)
                ->where('end_at', '>=', $now)
                ->get();

            return response()->json([
                'data' => [
                    'facility_id' => $facility->id,
                    'facility_name' => $facility->name,
                    'capacity' => $facility->capacity,
                    'current_bookings_count' => $currentBookings->count(),
                    'upcoming_bookings_count' => $upcomingBookings->count(),
                    'total_active_bookings' => $totalActiveBookings,
                    'occupancy_percentage' => round($occupancyPercentage, 2),
                    'active_blockouts_count' => $activeBlockouts->count(),
                    'current_bookings' => $currentBookings->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'student_name' => $booking->student->user->name ?? 'Unknown',
                            'student_uid' => $booking->student->student_uid ?? null,
                            'start_at' => $booking->start_at->toISOString(),
                            'end_at' => $booking->end_at->toISOString(),
                            'purpose' => $booking->purpose,
                        ];
                    }),
                    'upcoming_bookings' => $upcomingBookings->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'student_name' => $booking->student->user->name ?? 'Unknown',
                            'student_uid' => $booking->student->student_uid ?? null,
                            'start_at' => $booking->start_at->toISOString(),
                            'end_at' => $booking->end_at->toISOString(),
                            'purpose' => $booking->purpose,
                        ];
                    }),
                    'active_blockouts' => $activeBlockouts->map(function ($blockout) {
                        return [
                            'id' => $blockout->id,
                            'start_at' => $blockout->start_at->toISOString(),
                            'end_at' => $blockout->end_at->toISOString(),
                            'reason' => $blockout->reason,
                        ];
                    }),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch facility occupancy', [
                'error' => $e->getMessage(),
                'facility_id' => $facility->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to fetch occupancy data. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get no-show alerts for a facility
     * 
     * GET /api/v1/sports/facilities/{facility}/no-show-alerts
     */
    public function noShowAlerts(Request $request, SportsFacility $facility): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $facility);

        try {
            $now = now();
            $fifteenMinutesAgo = $now->copy()->subMinutes(15);

            // Get bookings that should have started but haven't been checked in
            // Bookings that started 0-15 minutes ago but status is still 'active' (not checked in)
            $noShowAlerts = $facility->activeBookings()
                ->where('start_at', '>=', $fifteenMinutesAgo)
                ->where('start_at', '<=', $now)
                ->where('status', 'active') // Still active, meaning no check-in
                ->with(['student.user'])
                ->orderBy('start_at', 'asc')
                ->get()
                ->map(function ($booking) use ($now) {
                    $minutesLate = $now->diffInMinutes($booking->start_at);
                    return [
                        'id' => $booking->id,
                        'student_name' => $booking->student->user->name ?? 'Unknown',
                        'student_uid' => $booking->student->student_uid ?? null,
                        'start_at' => $booking->start_at->toISOString(),
                        'minutes_late' => $minutesLate,
                        'status' => $minutesLate >= 15 ? 'no_show' : 'approaching',
                    ];
                });

            return response()->json([
                'data' => $noShowAlerts,
                'count' => $noShowAlerts->count(),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch no-show alerts', [
                'error' => $e->getMessage(),
                'facility_id' => $facility->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to fetch no-show alerts. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
