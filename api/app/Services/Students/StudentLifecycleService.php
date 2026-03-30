<?php

namespace App\Services\Students;

use App\Jobs\SendStudentArchiveNotification;
use App\Models\ActivityFeedEntry;
use App\Models\RoomAllocation;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentLifecycleService
{
    public function archive(Student $student, Carbon $archivedAt, ?string $reason = null): void
    {
        DB::transaction(function () use ($student, $archivedAt, $reason): void {
            $student->roomAllocations()
                ->where('is_active', true)
                ->with('roomBed')
                ->lockForUpdate()
                ->get()
                ->each(function (RoomAllocation $allocation) use ($archivedAt): void {
                    $allocation->forceFill([
                        'is_active' => false,
                        'effective_to' => $archivedAt,
                        'checkout_status' => 'archived',
                    ])->save();

                    if ($allocation->roomBed) {
                        $allocation->roomBed->forceFill([
                            'status' => 'available',
                            'released_at' => $archivedAt,
                        ])->save();
                    }
                });

            $student->forceFill([
                'archived_at' => $archivedAt,
                'archived_reason' => $reason,
                'archived_by' => auth()->id(),
            ])->save();

            ActivityFeedEntry::create([
                'tenant_id' => $student->tenant_id,
                'type' => 'student.archived',
                'channel' => 'system',
                'related_type' => Student::class,
                'related_id' => (string) $student->id,
                'title' => 'Student archived',
                'body' => $student->user?->name,
                'metadata' => [
                    'student_id' => $student->id,
                    'reason' => $reason,
                ],
                'created_by' => auth()->id(),
            ]);
        });

        SendStudentArchiveNotification::dispatch(
            $student->id,
            $archivedAt->toIso8601String(),
            $reason
        );
    }

    public function restore(Student $student): void
    {
        $student->forceFill([
            'archived_at' => null,
            'archived_reason' => null,
            'archived_by' => null,
        ])->save();

        ActivityFeedEntry::create([
            'tenant_id' => $student->tenant_id,
            'type' => 'student.restored',
            'channel' => 'system',
            'related_type' => Student::class,
            'related_id' => (string) $student->id,
            'title' => 'Student restored',
            'body' => $student->user?->name,
            'metadata' => [
                'student_id' => $student->id,
            ],
            'created_by' => auth()->id(),
        ]);
    }
}
