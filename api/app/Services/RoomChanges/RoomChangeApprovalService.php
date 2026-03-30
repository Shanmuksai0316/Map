<?php

namespace App\Services\RoomChanges;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Jobs\SendRoomChangeDecisionNotification;
use App\Models\ActivityFeedEntry;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RoomChangeApprovalService
{
    public function approve(RoomChange $roomChange, RoomBed $bed, Carbon $effectiveFrom, ?string $note = null): RoomChange
    {
        if ($roomChange->status !== 'pending') {
            throw ValidationException::withMessages([
                'room_change' => 'Only pending requests can be approved.',
            ]);
        }

        if ($roomChange->tenant_id !== $bed->tenant_id) {
            throw ValidationException::withMessages([
                'room_bed_id' => 'Bed belongs to a different tenant.',
            ]);
        }

        if (! in_array($bed->status, ['available', 'maintenance'], true)) {
            throw ValidationException::withMessages([
                'room_bed_id' => 'Bed is not available.',
            ]);
        }

        $student = $roomChange->student()->with('roomAllocations.roomBed')->firstOrFail();

        DB::transaction(function () use ($student, $bed, $effectiveFrom, $roomChange, $note): void {
            // End existing allocations
            $student->roomAllocations()
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->each(function (RoomAllocation $allocation) use ($effectiveFrom): void {
                    $allocation->forceFill([
                        'is_active' => false,
                        'effective_to' => $effectiveFrom,
                    ])->save();

                    $allocation->roomBed?->forceFill([
                        'status' => 'available',
                        'released_at' => $effectiveFrom,
                    ])->save();
                });

            $bed->forceFill([
                'status' => 'occupied',
                'occupied_at' => $effectiveFrom,
                'released_at' => null,
            ])->save();

            $periodMonths = config('checkouts.default_period_months', 18);
            $expectedCheckoutAt = $effectiveFrom->copy()->addMonths($periodMonths);

            RoomAllocation::query()->create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'room_bed_id' => $bed->id,
                'hostel_id' => $bed->hostel_id,
                'effective_from' => $effectiveFrom,
                'is_active' => true,
                'note' => $note,
                'expected_checkout_at' => $expectedCheckoutAt,
                'checkout_status' => 'pending',
            ]);

            $roomChange->forceFill([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => null,
                'last_escalated_at' => null,
                'last_reminded_at' => null,
            ])->save();

            ActivityFeedEntry::create([
                'tenant_id' => $roomChange->tenant_id,
                'type' => 'room_change.approved',
                'channel' => 'system',
                'related_type' => RoomChange::class,
                'related_id' => (string) $roomChange->id,
                'title' => 'Room change approved',
                'body' => $roomChange->student?->user?->name,
                'metadata' => [
                    'room_change_id' => $roomChange->id,
                    'room_bed_id' => $bed->id,
                ],
                'created_by' => auth()->id(),
            ]);
        });

        // Notifications should not break the approval flow (queue may be sync in some envs)
        try {
            SendRoomChangeDecisionNotification::dispatch((int) $roomChange->id, 'approved');
        } catch (\Throwable $e) {
            Log::warning('Room change approved but notification dispatch failed', [
                'room_change_id' => $roomChange->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $roomChange->fresh(['student', 'hostel']);
    }

    public function reject(RoomChange $roomChange, string $reason): RoomChange
    {
        if ($roomChange->status !== 'pending') {
            throw ValidationException::withMessages([
                'room_change' => 'Only pending requests can be rejected.',
            ]);
        }

        // Ensure student relationship is loaded before saving
        $roomChange->loadMissing('student.user');

        DB::transaction(function () use ($roomChange, $reason): void {
            $roomChange->forceFill([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'last_escalated_at' => null,
                'last_reminded_at' => null,
            ])->save();

            // Activity feed entry creation should not break the rejection flow
            try {
                ActivityFeedEntry::create([
                    'tenant_id' => $roomChange->tenant_id,
                    'type' => 'room_change.rejected',
                    'channel' => 'system',
                    'related_type' => RoomChange::class,
                    'related_id' => (string) $roomChange->id,
                    'title' => 'Room change rejected',
                    'body' => $roomChange->student?->user?->name ?? 'Unknown',
                    'metadata' => [
                        'room_change_id' => $roomChange->id,
                        'reason' => $reason,
                    ],
                    'created_by' => auth()->id(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Room change rejected but activity feed entry creation failed', [
                    'room_change_id' => $roomChange->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        // Notifications should not break the rejection flow (queue may be sync in some envs)
        try {
            SendRoomChangeDecisionNotification::dispatch((int) $roomChange->id, 'rejected', $reason);
        } catch (\Throwable $e) {
            Log::warning('Room change rejected but notification dispatch failed', [
                'room_change_id' => $roomChange->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $roomChange->fresh(['student']);
    }
}
