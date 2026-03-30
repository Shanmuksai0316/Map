<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\User;
use App\Services\Attendance\LeaveDeriver;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly LeaveDeriver $leaveDeriver,
        private readonly AuditLogger $auditLogger
    ) {}

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();

        // Find first visible hostel for the user
        $hostelIds = \App\Support\HostelScope::idsFor($user);
        $hostel = Hostel::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $hostelIds)
            ->first();

        if (! $hostel) {
            return response()->json(['error' => 'No hostel found'], 404);
        }

        $session = AttendanceSession::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hostel_id', $hostel->id)
            ->where('kind', 'night_check')
            ->whereDate('scheduled_at', now('Asia/Kolkata')->toDateString())
            ->first();

        if (! $session) {
            return response()->json(['error' => 'No session found for today'], 404);
        }

        $this->authorize('viewSession', $session);

        return response()->json([
            'id' => $session->id,
            'hostel_id' => $session->hostel_id,
            'session_date' => $session->metadata['session_date'] ?? $session->scheduled_at->toDateString(),
            'open_at' => $session->metadata['open_at'] ?? null,
            'close_at' => $session->metadata['close_at'] ?? null,
            'status' => $session->status,
        ]);
    }

    public function rooms(Request $request, AttendanceSession $session): JsonResponse
    {
        $this->authorize('listRooms', $session);

        // Simplified version - return basic room info for now
        $rooms = Room::query()
            ->where('hostel_id', $session->hostel_id)
            ->get()
            ->map(function (Room $room) {
                return [
                    'room_id' => $room->id,
                    'block_code' => $room->block_code ?? 'A',
                    'floor_code' => $room->floor_code ?? '1',
                    'room_no' => $room->room_no ?? '101',
                    'total_students' => 0, // TODO: Calculate from room allocations
                    'marked_present' => 0,
                    'marked_absent' => 0,
                    'marked_leave' => 0,
                    'unmarked' => 0,
                ];
            });

        return response()->json($rooms);
    }

    public function roster(Request $request, AttendanceSession $session, Room $room): JsonResponse
    {
        $this->authorize('viewRoster', $session);

        if ($room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room does not belong to session hostel'], 400);
        }

        $roster = $this->rosterForRoom($session, $room->id);

        return response()->json($roster);
    }

    public function mark(Request $request, AttendanceSession $session, Room $room): JsonResponse
    {
        $this->authorize('mark', $session);

        if ($room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room does not belong to session hostel'], 400);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:present,absent'],
            'comment' => ['nullable', 'string', 'max:200'],
        ]);

        $studentId = $validated['student_id'];
        $status = $validated['status'];
        $comment = $validated['comment'] ?? null;

        // Check if student is in the room and get the Student record
        $roomAllocation = RoomAllocation::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('is_active', true)
            ->whereHas('student', function ($query) use ($studentId) {
                $query->where('user_id', $studentId);
            })
            ->whereHas('roomBed', function ($query) use ($room) {
                $query->where('room_id', $room->id);
            })
            ->with('student')
            ->first();

        if (! $roomAllocation) {
            return response()->json(['error' => 'Student not found in this room'], 400);
        }

        $studentRecord = $roomAllocation->student;

        // Check if student is on leave (locked)
        $leaveStudentIds = $this->leaveDeriver->leaveStudentIds($session);
        if ($leaveStudentIds->contains($studentRecord->user_id)) {
            throw ValidationException::withMessages([
                'student_id' => ['Cannot mark student who is on approved leave'],
            ]);
        }

        DB::transaction(function () use ($session, $studentRecord, $status, $comment, $request): void {
            AttendanceMark::query()->updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentRecord->id, // Use Student ID, not User ID
                ],
                [
                    'tenant_id' => $session->tenant_id,
                    'status' => $status,
                    'note' => $status === 'absent' ? $comment : null,
                    'marked_by' => $request->user()->id,
                    'marked_at' => now(),
                ]
            );

            $session->recalcCounts();
        });

        return response()->json(['status' => 'ok']);
    }

    public function submitRoom(Request $request, AttendanceSession $session, Room $room): JsonResponse
    {
        $this->authorize('submitRoom', [$session, $room->id]);

        if ($room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room does not belong to session hostel'], 400);
        }

        $roster = $this->rosterForRoom($session, $room->id);
        $leaveStudentIds = $this->leaveDeriver->leaveStudentIds($session);

        // Check for unmarked non-leave students
        $unmarkedNonLeave = $roster->filter(function ($student) use ($leaveStudentIds) {
            return $student['current_status'] === 'unmarked' && !$leaveStudentIds->contains($student['student_id']);
        });

        if ($unmarkedNonLeave->isNotEmpty()) {
            throw ValidationException::withMessages([
                'room' => ['All non-leave students must be marked before submit.'],
            ]);
        }

        $this->auditLogger->log('attendance.room_submitted', $session, [
            'room_id' => $room->id,
        ]);

        return response()->json(['ok' => true]);
    }

    public function batchMark(Request $request, AttendanceSession $session, Room $room): JsonResponse
    {
        $this->authorize('batchMark', [$session, $room->id]);

        if ($room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room does not belong to session hostel'], 400);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'max:100'],
            'items.*.student_id' => ['required', 'integer', 'exists:users,id'],
            'items.*.status' => ['required', 'string', 'in:present,absent'],
            'items.*.comment' => ['nullable', 'string', 'max:200'],
        ]);

        $leaveStudentIds = $this->leaveDeriver->leaveStudentIds($session);
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($session, $validated, $leaveStudentIds, &$updated, &$skipped, $request, $room): void {
            foreach ($validated['items'] as $item) {
                if ($leaveStudentIds->contains($item['student_id'])) {
                    $skipped++;
                    continue;
                }

                // Find the Student record for this User ID
                $roomAllocation = RoomAllocation::query()
                    ->where('tenant_id', $session->tenant_id)
                    ->where('is_active', true)
                    ->whereHas('student', function ($query) use ($item) {
                        $query->where('user_id', $item['student_id']);
                    })
                    ->whereHas('roomBed', function ($query) use ($room) {
                        $query->where('room_id', $room->id);
                    })
                    ->with('student')
                    ->first();

                if (!$roomAllocation) {
                    $skipped++;
                    continue;
                }

                $studentRecord = $roomAllocation->student;

                AttendanceMark::query()->updateOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'student_id' => $studentRecord->id, // Use Student ID, not User ID
                    ],
                    [
                        'tenant_id' => $session->tenant_id,
                        'status' => $item['status'],
                        'note' => $item['status'] === 'absent' ? ($item['comment'] ?? null) : null,
                        'marked_by' => $request->user()->id,
                        'marked_at' => now(),
                    ]
                );
                $updated++;
            }

            $session->recomputeCounts();
        });

        $summary = $this->roomSummary($session, $room->id);

        return response()->json([
            'updated' => $updated,
            'skipped' => $skipped,
            'summary' => $summary,
        ]);
    }

    private function rosterForRoom(AttendanceSession $session, int $roomId)
    {
        $leaveStudentIds = $this->leaveDeriver->leaveStudentIds($session);

        $roster = DB::table('room_allocations as ra')
            ->join('room_beds as rb', 'rb.id', '=', 'ra.room_bed_id')
            ->join('rooms as r', 'r.id', '=', 'rb.room_id')
            ->join('students as st', 'st.id', '=', 'ra.student_id')
            ->join('users as u', 'u.id', '=', 'st.user_id')
            ->leftJoin('attendance_logs as am', function ($j) use ($session) {
                $j->on('am.student_id', '=', 'st.id')
                  ->where('am.attendance_session_id', '=', $session->id);
            })
            ->where('ra.is_active', 1)
            ->where('r.id', $roomId)
            ->where('ra.tenant_id', $session->tenant_id)
            ->select([
                'u.id as student_id', // Return user ID for API compatibility
                'u.name',
                DB::raw('COALESCE(am.status, "unmarked") as current_status'),
                'am.note as comment',
            ])
            ->get()
            ->map(function ($student) use ($leaveStudentIds) {
                $isLeave = $leaveStudentIds->contains($student->student_id);
                $currentStatus = $isLeave ? 'leave' : $student->current_status;
                $locked = $isLeave;

                return [
                    'student_id' => $student->student_id,
                    'name' => $student->name,
                    'current_status' => $currentStatus,
                    'locked' => $locked,
                    'comment' => $student->comment,
                ];
            });

        return $roster;
    }

    private function roomSummary(AttendanceSession $session, int $roomId): array
    {
        $roster = $this->rosterForRoom($session, $roomId);

        return [
            'total' => $roster->count(),
            'present' => $roster->where('current_status', 'present')->count(),
            'absent' => $roster->where('current_status', 'absent')->count(),
            'leave' => $roster->where('current_status', 'leave')->count(),
            'unmarked' => $roster->where('current_status', 'unmarked')->count(),
        ];
    }

    public function history(Request $r) {
        $user = auth()->user();
        $hostelIds = \App\Support\HostelScope::idsFor($user);
        $days = min(max((int)$r->query('days', 14), 1), 60);
        $sessions = AttendanceSession::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('hostel_id', $hostelIds)
            ->whereBetween('scheduled_at', [now()->subDays($days), now()->addDay()])
            ->orderByDesc('scheduled_at')->limit(60)->get(['id','scheduled_at','status','meta']);
        return response()->json($sessions);
    }

    public function room($sid, $rid) {
        $user = auth()->user();
        $hostelIds = \App\Support\HostelScope::idsFor($user);
        $session = AttendanceSession::where('tenant_id', $user->tenant_id)
            ->whereIn('hostel_id', $hostelIds)
            ->findOrFail($sid);
        $this->authorize('viewSession', $session);

        $students = RoomBed::where('room_id', $rid)->with('student.user:id,name')->get()
            ->map(fn($b)=>[
                'student_id'=>$b->student?->id,
                'name'=>$b->student?->user?->name,
                'map_student_id'=>$b->student?->map_student_id,
                'status'=> AttendanceMark::where([
                    'attendance_session_id'=>$sid,'student_id'=>$b->student?->id
                ])->value('status')
            ])->filter(fn($row)=>$row['student_id']);

        return response()->json(['students'=>$students->values()]);
    }


    public function submit($sid, $rid) {
        $session = AttendanceSession::where('tenant_id', auth()->user()->tenant_id)->findOrFail($sid);
        $this->authorize('updateSession', $session);
        // Optional: flip a room-level submitted flag in meta or create a "submitted" log
        return response()->json(['ok'=>true]);
    }
}
