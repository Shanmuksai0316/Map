<?php

namespace App\Services\Rooms;

use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Services\Rooms\Concerns\HandlesAllocations;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;

class AutoAllocationService
{
    use HandlesAllocations;

    /**
     * Generate an Excel template.
     *
     * @param  string  $mode  pre-assignment|auto-allocation
     */
    public function generateTemplate(string $mode, ?int $hostelId = null): string
    {
        $filename = sprintf('auto-allocation/%s-%s.xlsx', $mode, now()->format('YmdHis'));
        Storage::makeDirectory('auto-allocation');
        $path = Storage::path($filename);

        $writer = SimpleExcelWriter::create($path);

        if ($mode === 'pre-assignment') {
            $rows = RoomBed::query()
                ->with(['room', 'hostel'])
                ->when($hostelId, fn ($query) => $query->where('hostel_id', $hostelId))
                ->orderBy('hostel_id')
                ->get()
                ->map(function (RoomBed $bed) {
                    return [
                        'hostel_code' => $bed->hostel?->code,
                        'hostel_name' => $bed->hostel?->name,
                        'room_number' => $bed->room?->number,
                        'bed_code' => $bed->code,
                        'status' => $bed->status,
                    ];
                })
                ->toArray();

            $writer->addRows($rows ?: [[
                'hostel_code' => null,
                'hostel_name' => null,
                'room_number' => null,
                'bed_code' => null,
                'status' => null,
            ]]);
        } else {
            $rows = $this->unassignedStudentsQuery($hostelId)
                ->with(['user', 'hostel', 'preferredHostel'])
                ->get()
                ->map(function (Student $student) {
                    return [
                        'student_uid' => $student->student_uid,
                        'name' => $student->user?->name,
                        'phone' => $student->user?->phone,
                        'current_hostel' => $student->hostel?->name,
                        'preferred_hostel' => $student->preferredHostel?->name,
                        'preferred_room_type' => $student->preferred_room_type,
                        'preferred_sharing' => $student->preferred_sharing,
                    ];
                })
                ->toArray();

            $writer->addRows($rows ?: [[
                'student_uid' => null,
                'name' => null,
                'phone' => null,
                'current_hostel' => null,
                'preferred_hostel' => null,
                'preferred_room_type' => null,
                'preferred_sharing' => null,
            ]]);
        }

        $writer->close();

        return $path;
    }

    public function preview(?int $hostelId = null, int $limit = 50): array
    {
        $students = $this->unassignedStudentsQuery($hostelId)
            ->with(['user', 'preferredHostel'])
            ->limit($limit)
            ->get();

        $beds = $this->availableBedsQuery($hostelId)
            ->with(['room', 'hostel'])
            ->get()
            ->keyBy('id');

        $suggestions = [];

        foreach ($students as $student) {
            $match = $this->findBestBedForStudent($student, $beds);

            if (! $match) {
                $suggestions[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->user?->name,
                    'student_uid' => $student->student_uid,
                    'preferred_hostel_id' => $student->preferred_hostel_id,
                    'room_bed_id' => null,
                    'room_label' => null,
                    'score' => 0,
                    'reason' => 'No beds available for preferences',
                ];
                continue;
            }

            $beds->forget($match['bed']->id);

            $suggestions[] = [
                'student_id' => $student->id,
                'student_name' => $student->user?->name,
                'student_uid' => $student->student_uid,
                'preferred_hostel_id' => $student->preferred_hostel_id,
                'room_bed_id' => $match['bed']->id,
                'room_label' => sprintf(
                    '%s - %s (%s)',
                    $match['bed']->hostel?->name,
                    $match['bed']->room?->number,
                    $match['bed']->code
                ),
                'score' => $match['score'],
                'reason' => $match['reason'],
            ];
        }

        return $suggestions;
    }

    public function commit(array $allocations, ?string $note = null, ?CarbonInterface $effectiveFrom = null): array
    {
        $results = [];
        $effectiveDate = $effectiveFrom ?? now();

        DB::transaction(function () use (&$results, $allocations, $note, $effectiveDate): void {
            foreach ($allocations as $allocation) {
                $student = Student::with('roomAllocations')->findOrFail($allocation['student_id']);
                $bed = RoomBed::with('room', 'hostel')->findOrFail($allocation['room_bed_id']);

                $this->ensureBedAvailable($bed, Carbon::parse($allocation['effective_from'] ?? $effectiveDate));

                $created = $this->allocate(
                    $student,
                    $bed,
                    Carbon::parse($allocation['effective_from'] ?? $effectiveDate),
                    Arr::get($allocation, 'note', $note)
                );

                $results[] = $created->load('student', 'roomBed');
            }
        });

        return $results;
    }

    protected function unassignedStudentsQuery(?int $hostelId = null)
    {
        return Student::query()
            ->whereDoesntHave('roomAllocations', function ($query) {
                $query->where('is_active', true);
            })
            ->when($hostelId, fn ($query) => $query->where('hostel_id', $hostelId));
    }

    protected function availableBedsQuery(?int $hostelId = null)
    {
        return RoomBed::query()
            ->where('status', 'available')
            ->when($hostelId, fn ($query) => $query->where('hostel_id', $hostelId));
    }

    protected function findBestBedForStudent(Student $student, Collection $beds): ?array
    {
        $preferred = $beds->filter(function (RoomBed $bed) use ($student) {
            return $student->preferred_hostel_id && $bed->hostel_id === $student->preferred_hostel_id;
        });

        $candidate = $preferred->first() ?: $beds->first();

        if (! $candidate) {
            return null;
        }

        $score = 50;
        $reason = 'Matched on available bed';

        if ($student->preferred_hostel_id && $student->preferred_hostel_id === $candidate->hostel_id) {
            $score += 25;
            $reason = 'Matches preferred hostel';
        }

        if ($student->preferred_room_type && $candidate->room?->room_type === $student->preferred_room_type) {
            $score += 15;
            $reason = 'Matches room type preference';
        }

        if ($student->preferred_sharing && $candidate->room?->capacity === $this->sharingToCapacity($student->preferred_sharing)) {
            $score += 10;
            $reason = 'Matches sharing preference';
        }

        return [
            'bed' => $candidate,
            'score' => min($score, 100),
            'reason' => $reason,
        ];
    }

    protected function sharingToCapacity(string $preference): ?int
    {
        return match ($preference) {
            'single' => 1,
            'double' => 2,
            'triple' => 3,
            'quad' => 4,
            default => null,
        };
    }
}

