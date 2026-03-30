<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacilityBookingResource;
use App\Models\FacilityBooking;
use App\Models\SportsFacility;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notify\PushNotifier;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FacilityBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', FacilityBooking::class);

        $query = FacilityBooking::query()
            ->with(['facility', 'student.user'])
            ->when($request->filled('facility_id'), fn ($q) => $q->where('facility_id', $request->integer('facility_id')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->integer('student_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('start_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('start_at', '<=', $request->date('date_to')));

        // Filter by user role permissions
        $user = Auth::user();
        if (!$user || !$user->hasAnyRole(['Campus Manager', 'Sports Manager', 'Super Admin'])) {
            $query->where('student_id', Auth::user()->student->id ?? -1);
        }

        $bookings = $query->orderBy('start_at')->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => FacilityBookingResource::collection($bookings)
        ]);
    }

    public function show(FacilityBooking $booking): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $booking);

        return response()->json([
            'data' => FacilityBookingResource::make($booking->load(['facility', 'student.user']))
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', FacilityBooking::class);

        $validated = $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'purpose' => 'nullable|string|max:255',
            'participants' => 'integer|min:1|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $facility = SportsFacility::findOrFail($validated['facility_id']);
        $student = Auth::user()->student ?? throw new \Exception('User must be a student');

        // Check facility availability (includes blockouts)
        if (!$facility->isAvailable($validated['start_at'], $validated['end_at'])) {
            // Check if it's blocked by a blockout
            if ($facility->hasBlockout($validated['start_at'], $validated['end_at'])) {
                return response()->json([
                    'error' => 'Facility is blocked for the selected time slot'
                ], Response::HTTP_CONFLICT);
            }
            return response()->json([
                'error' => 'Facility is not available for the selected time slot'
            ], Response::HTTP_CONFLICT);
        }

        // Check student doesn't have overlapping bookings
        $overlappingBooking = FacilityBooking::where('student_id', $student->id)
            ->where('status', 'active')
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_at', [$validated['start_at'], $validated['end_at']])
                      ->orWhereBetween('end_at', [$validated['start_at'], $validated['end_at']])
                      ->orWhere(function ($q) use ($validated) {
                          $q->where('start_at', '<=', $validated['start_at'])
                            ->where('end_at', '>=', $validated['end_at']);
                      });
            })
            ->exists();

        if ($overlappingBooking) {
            return response()->json([
                'error' => 'You already have a booking that conflicts with this time slot'
            ], Response::HTTP_CONFLICT);
        }

        $booking = DB::transaction(function () use ($facility, $student, $validated) {
            return FacilityBooking::create([
                'facility_id' => $validated['facility_id'],
                'student_id' => $student->id,
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'status' => 'active',
                'purpose' => $validated['purpose'],
                'participants' => $validated['participants'] ?? 1,
                'notes' => $validated['notes'],
            ]);
        });

        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        if ($tenantId !== '') {
            $timeRange = $booking->start_at->format('d M, H:i') . ' - ' . $booking->end_at->format('H:i');
            $this->notifySportsManagers(
                $tenantId,
                'sports_manager.booking_confirmed',
                [
                    'facility_name' => $facility->name ?? 'Sports facility',
                    'time_range' => $timeRange,
                ],
                [
                    'type' => 'sports_booking_confirmed',
                    'booking_id' => (string) $booking->id,
                ]
            );
        }

        return response()->json([
            'data' => FacilityBookingResource::make($booking->load(['facility', 'student.user']))
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, FacilityBooking $booking): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $booking);

        $validated = $request->validate([
            'start_at' => 'date|after:now',
            'end_at' => 'date|after:start_at',
            'purpose' => 'nullable|string|max:255',
            'participants' => 'integer|min:1|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check facility availability (exclude current booking)
        $facility = $booking->facility;
        if (!$facility->isAvailable($validated['start_at'], $validated['end_at'])) {
            // Check if the conflict is with the current booking
            $currentBookingConflict = $facility->bookings()
                ->where('status', 'active')
                ->where('id', $booking->id)
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('start_at', [$validated['start_at'], $validated['end_at']])
                          ->orWhereBetween('end_at', [$validated['start_at'], $validated['end_at']]);
                })
                ->exists();

            if (!$currentBookingConflict) {
                return response()->json([
                    'error' => 'Facility is not available for the selected time slot'
                ], Response::HTTP_CONFLICT);
            }
        }

        $booking->fill($validated);
        $booking->save();

        return response()->json([
            'data' => FacilityBookingResource::make($booking->load(['facility', 'student.user']))
        ]);
    }

    public function destroy(FacilityBooking $booking): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('delete', $booking);

        if (!$booking->canCancel()) {
            return response()->json([
                'error' => 'Booking cannot be cancelled at this time'
            ], Response::HTTP_CONFLICT);
        }

        $booking->loadMissing('facility');
        $booking->cancel();

        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        if ($tenantId !== '') {
            $this->notifySportsManagers(
                $tenantId,
                'sports_manager.booking_canceled',
                [
                    'reason' => 'Cancelled by ' . (Auth::user()?->name ?? 'user'),
                ],
                [
                    'type' => 'sports_booking_canceled',
                    'booking_id' => (string) $booking->id,
                ]
            );
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function cancel(FacilityBooking $booking): JsonResponse
    {
        abort_unless(Feature::isEnabled('sports_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $booking);

        if (!$booking->canCancel()) {
            return response()->json([
                'error' => 'Booking cannot be cancelled at this time'
            ], Response::HTTP_CONFLICT);
        }

        $booking->loadMissing('facility');
        $booking->cancel();

        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        if ($tenantId !== '') {
            $this->notifySportsManagers(
                $tenantId,
                'sports_manager.booking_canceled',
                [
                    'reason' => 'Cancelled by ' . (Auth::user()?->name ?? 'user'),
                ],
                [
                    'type' => 'sports_booking_canceled',
                    'booking_id' => (string) $booking->id,
                ]
            );
        }

        return response()->json([
            'data' => FacilityBookingResource::make($booking->load(['facility', 'student.user']))
        ]);
    }

    private function notifySportsManagers(string $tenantId, string $template, array $vars, array $data = []): void
    {
        try {
            /** @var NotificationRecipients $recipients */
            $recipients = app(NotificationRecipients::class);
            /** @var PushNotifier $push */
            $push = app(PushNotifier::class);

            foreach ($recipients->sportsManagersForTenant($tenantId) as $sportsManager) {
                $push->toUserTemplate($sportsManager->id, $template, $vars, $data);
            }
        } catch (\Throwable $e) {
            Log::warning('facility_booking.sports_manager_notification_failed', [
                'tenant_id' => $tenantId,
                'template' => $template,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
