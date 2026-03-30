<?php

namespace App\Services\Rooms\Concerns;

use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Services\Notify\PushNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

trait HandlesAllocations
{
    protected function ensureBedAvailable(RoomBed $bed, CarbonInterface $effectiveFrom): void
    {
        if ($bed->status !== 'available') {
            throw new RuntimeException('Bed is not available for allocation.');
        }
    }

    protected function allocate(Student $student, RoomBed $bed, CarbonInterface $effectiveFrom, ?string $note = null): RoomAllocation
    {
        $existing = $student->roomAllocations()
            ->where('is_active', true)
            ->lockForUpdate()
            ->get();

        foreach ($existing as $allocation) {
            $allocation->forceFill([
                'is_active' => false,
                'effective_to' => $effectiveFrom,
            ])->save();

            $allocation->roomBed->forceFill([
                'status' => 'available',
                'released_at' => $effectiveFrom,
            ])->save();
        }

        $bed->forceFill([
            'status' => 'occupied',
            'occupied_at' => $effectiveFrom,
            'released_at' => null,
        ])->save();

        $periodMonths = config('checkouts.default_period_months', 18);
        $expectedCheckoutAt = Carbon::parse($effectiveFrom)->addMonths($periodMonths);

        $allocation = RoomAllocation::query()->create([
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

        // Notify student about room allocation approval (best-effort)
        try {
            $user = $student->user;
            if ($user) {
                /** @var PushNotifier $push */
                $push = app(PushNotifier::class);

                $room = $bed->room;
                $hostel = $bed->hostel;

                $push->toUserTemplate(
                    $user->id,
                    'student.room_allocation_approved',
                    [
                        'hostel_name'    => $hostel?->name ?? 'Hostel',
                        'floor_number'   => $room?->floor_code ?? '',
                        'room_number'    => $room?->number ?? '',
                        'bed_label'      => $bed->code,
                        'effective_date' => Carbon::parse($effectiveFrom)->toDateString(),
                    ],
                    [
                        'type'             => 'room_allocation_approved',
                        'room_allocation_id' => $allocation->id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // swallow notification failures
        }

        return $allocation;
    }
}

