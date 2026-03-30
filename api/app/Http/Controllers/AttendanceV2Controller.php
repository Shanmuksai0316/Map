<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\Models\AttendanceRoomSubmission;
use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Models\AttendanceLog;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\HostelScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceV2Controller extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Find first visible hostel for the user
        $hostelIds = HostelScope::idsFor($user);
        $hostel = Hostel::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $hostelIds)
            ->first();

        if (!$hostel) {
            return response()->json(['error' => 'No hostel found'], 404);
        }

        $session = AttendanceSessionV2::query()
            ->forTenant($user->tenant_id)
            ->forHostel($hostel->id)
            ->today()
            ->first();

        if (!$session) {
            return response()->json([
                'data' => null
            ], 200); // Return 200 with null data instead of 204
        }

        // Calculate counts
        $counts = $this->calculateSessionCounts($session);

        return response()->json([
            'data' => [
                'id' => $session->id,
                'hostel_id' => $session->hostel_id,
                'date' => $session->session_date,
                'window' => $session->metadata['window'] ?? [
                    'start' => $session->scheduled_at?->toISOString(),
                    'end' => $session->scheduled_at?->addHours(2)->toISOString(),
                ],
                'status' => strtolower($session->status), // Ensure lowercase
                'counts' => $counts,
            ]
        ]);
    }

    public function rooms(Request $request, AttendanceSessionV2 $session): JsonResponse
    {
        $user = $request->user();
        
        // Check authorization
        $hostelIds = HostelScope::idsFor($user);
        if (!in_array($session->hostel_id, $hostelIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rooms = Room::query()
            ->where('hostel_id', $session->hostel_id)
            ->with(['beds.allocations.student.user']) // Eager load to avoid N+1
            ->get()
            ->map(function (Room $room) use ($session) {
                $students = $room->beds->flatMap(fn($bed) => $bed->allocations->pluck('student'))->filter();
                $total = $students->count();
                
                if ($total === 0) {
                    return null;
                }

                $studentIds = $students->pluck('id');
                
                // Use single query to get all attendance data
                $attendanceData = AttendanceLog::where('session_id', $session->id)
                    ->whereIn('student_id', $studentIds)
                    ->selectRaw('
                        COUNT(*) as marked,
                        SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent
                    ')
                    ->first();

                $marked = $attendanceData->marked ?? 0;
                $present = $attendanceData->present ?? 0;
                $absent = $attendanceData->absent ?? 0;
                $unmarked = $total - $marked;

                return [
                    'room_id' => $room->id,
                    'room' => ($room->block_code ?? 'A') . '-' . ($room->floor_code ?? '1') . $room->number,
                    'counts' => [
                        'total' => $total,
                        'present' => $present,
                        'absent' => $absent,
                        'unmarked' => $unmarked,
                    ],
                    'percent_complete' => $total > 0 ? round(($marked / $total) * 100, 1) : 0,
                ];
            })
            ->filter();

        return response()->json(['data' => $rooms->values()]);
    }

    public function roster(Request $request, AttendanceSessionV2 $session, int $roomId): JsonResponse
    {
        $user = $request->user();
        
        // Check authorization
        $hostelIds = HostelScope::idsFor($user);
        if (!in_array($session->hostel_id, $hostelIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $room = Room::find($roomId);
        if (!$room || $room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room not found or does not belong to session hostel'], 404);
        }

        $roomBeds = RoomBed::where('room_id', $roomId)
            ->with('allocations.student.user')
            ->get();

        $students = $roomBeds->flatMap(function (RoomBed $bed) use ($session) {
            return $bed->allocations->filter(fn($allocation) => $allocation->is_active)->map(function ($allocation) use ($session) {
                $student = $allocation->student;
                if (!$student) {
                    return null;
                }

                $attendanceLog = AttendanceLog::where('session_id', $session->id)
                    ->where('student_id', $student->id)
                    ->first();

                // Check if student is on leave (integrate with outpass/leave system)
                $leave = $this->isStudentOnLeave($student->id, $session->session_date);

                return [
                    'student_id' => $student->id,
                    'name' => $student->user?->name ?? 'Unknown',
                    'mark' => $attendanceLog?->status ?? null,
                    'leave' => $leave,
                    'uid_masked' => $this->maskUid($student->map_student_id ?? ''),
                ];
            });
        })->filter();

        return response()->json([
            'data' => [
                'room' => ($room->block_code ?? 'A') . '-' . ($room->floor_code ?? '1') . $room->number,
                'students' => $students->values(),
            ]
        ]);
    }

    public function mark(Request $request, AttendanceSessionV2 $session, int $roomId): JsonResponse
    {
        $user = $request->user();
        
        // Check authorization
        $hostelIds = HostelScope::idsFor($user);
        if (!in_array($session->hostel_id, $hostelIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|integer',
            'mark' => 'required|in:present,absent',
            'idempotency_key' => 'required|string',
        ]);

        // Reject if leave is in payload (leave is derived from other systems)
        if ($request->has('leave')) {
            throw ValidationException::withMessages([
                'leave' => ['Leave status is derived automatically and cannot be set manually.']
            ]);
        }

        // Check idempotency cache first
        $cacheKey = "attendance_mark:{$user->tenant_id}:{$user->id}:{$validated['idempotency_key']}";
        $cachedResult = Cache::get($cacheKey);
        
        if ($cachedResult) {
            // Emit idempotency hit metric
            $this->auditLogger->log('ATTENDANCE_IDEMPOTENCY_REPLAYED', $session, [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'session_id' => $session->id,
                'student_id' => $validated['student_id'],
            ]);
            return response()->json($cachedResult);
        }

        $room = Room::find($roomId);
        if (!$room || $room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room not found or does not belong to session hostel'], 404);
        }

        // Verify student belongs to this room
        $allocation = \App\Models\RoomAllocation::whereHas('roomBed', function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })
            ->where('student_id', $validated['student_id'])
            ->where('is_active', true)
            ->first();

        if (!$allocation) {
            return response()->json(['error' => 'Student not found in this room'], 404);
        }

        // Create or update attendance log (idempotent)
        $attendanceLog = AttendanceLog::updateOrCreate(
            [
                'session_id' => $session->id,
                'student_id' => $validated['student_id'],
            ],
            [
                'tenant_id' => $session->tenant_id,
                'attendance_session_id' => $session->id, // Keep for legacy compatibility
                'session_id' => $session->id,
                'status' => $validated['mark'],
                'marked_at' => now(),
                'marked_by' => $user->id,
                'metadata' => ['idempotency_key' => $validated['idempotency_key']],
            ]
        );

        // Calculate updated counts for the room
        $counts = $this->calculateRoomCounts($session, $roomId);

        $response = [
            'ok' => true,
            'counts' => $counts,
        ];

        // Cache the result for idempotency (15 minutes)
        Cache::put($cacheKey, $response, 900);

        // Emit mark success metric
        $this->auditLogger->log('ATTENDANCE_MARK_SUCCESS', $session, [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'session_id' => $session->id,
            'student_id' => $validated['student_id'],
            'mark' => $validated['mark'],
            'room_id' => $roomId,
        ]);

        return response()->json($response);
    }

    public function submit(Request $request, AttendanceSessionV2 $session, int $roomId): JsonResponse
    {
        $user = $request->user();
        
        // Check authorization
        $hostelIds = HostelScope::idsFor($user);
        if (!in_array($session->hostel_id, $hostelIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $room = Room::find($roomId);
        if (!$room || $room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room not found or does not belong to session hostel'], 404);
        }

        // Check if all students are marked or on leave
        $allocations = \App\Models\RoomAllocation::whereHas('roomBed', function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })
            ->where('is_active', true)
            ->with('student')
            ->get();
            
        $students = $allocations->pluck('student')->filter();
        
        $markedCount = AttendanceLog::where('session_id', $session->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->count();

        $totalStudents = $students->count();
        
        if ($markedCount < $totalStudents) {
            return response()->json(['error' => 'All students must be marked before submitting'], 400);
        }

        DB::transaction(function () use ($session, $roomId, $user): void {
            // Record room submission with timestamp
            AttendanceRoomSubmission::updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'room_id' => $roomId,
                ],
                [
                    'tenant_id' => $session->tenant_id,
                    'submitted_by' => $user->id,
                    'submitted_at' => now('Asia/Kolkata'),
                ]
            );

            // Lock the room (mark as submitted in metadata)
            $session->update([
                'metadata' => array_merge($session->metadata ?? [], [
                    'submitted_rooms' => array_unique(array_merge(
                        $session->metadata['submitted_rooms'] ?? [],
                        [$roomId]
                    ))
                ])
            ]);
        });

        return response()->json([
            'ok' => true,
            'locked' => true,
        ]);
    }

    public function revealUid(Request $request, AttendanceSessionV2 $session, int $roomId, int $studentId): JsonResponse
    {
        $user = $request->user();
        
        // Check authorization
        $hostelIds = HostelScope::idsFor($user);
        if (!in_array($session->hostel_id, $hostelIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $room = Room::find($roomId);
        if (!$room || $room->hostel_id !== $session->hostel_id) {
            return response()->json(['error' => 'Room not found or does not belong to session hostel'], 404);
        }

        // Verify student belongs to this room
        $allocation = \App\Models\RoomAllocation::whereHas('roomBed', function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->with('student.user')
            ->first();

        if (!$allocation || !$allocation->student) {
            return response()->json(['error' => 'Student not found in this room'], 404);
        }

        $student = $allocation->student;

        // TODO: Implement OTP verification here
        // For now, we'll just log the reveal and return the UID
        
        // Log the PII reveal
        $this->auditLogger->log('PII_REVEAL', $session, [
            'tenant_id' => $session->tenant_id,
            'actor_user_id' => $user->id,
            'subject_type' => 'student',
            'subject_id' => $studentId,
            'session_id' => $session->id,
            'room_id' => $roomId,
            'student_name' => $student->user?->name ?? 'Unknown',
        ]);

        return response()->json([
            'data' => [
                'student_id' => $studentId,
                'uid' => $student->map_student_id ?? '',
                'name' => $student->user?->name ?? 'Unknown',
            ]
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $hostelIds = HostelScope::idsFor($user);
        
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90']
        ]);
        
        $days = $validated['days'] ?? 7;
        
        $sessions = AttendanceSessionV2::query()
            ->forTenant($user->tenant_id)
            ->whereIn('hostel_id', $hostelIds)
            ->whereBetween('session_date', [
                now('Asia/Kolkata')->subDays($days)->toDateString(),
                now('Asia/Kolkata')->addDay()->toDateString()
            ])
            ->orderByDesc('session_date')
            ->limit(100)
            ->get()
            ->map(function (AttendanceSessionV2 $session) {
                $counts = $this->calculateSessionCounts($session);
                $marked = ($counts['present'] ?? 0) + ($counts['absent'] ?? 0);
                $completionPercent = $counts['total'] > 0 
                    ? round(($marked / $counts['total']) * 100, 1)
                    : 0;
                
                // Calculate room counts
                $rooms = Room::where('hostel_id', $session->hostel_id)->get();
                $completedRooms = 0;
                $totalRooms = 0;
                
                foreach ($rooms as $room) {
                    $allocations = \App\Models\RoomAllocation::whereHas('roomBed', function ($query) use ($room) {
                            $query->where('room_id', $room->id);
                        })
                        ->where('is_active', true)
                        ->count();
                    
                    if ($allocations > 0) {
                        $totalRooms++;
                        
                        $roomMarks = AttendanceLog::where('session_id', $session->id)
                            ->whereHas('student', function ($query) use ($room) {
                                $query->whereHas('allocations.roomBed', function ($q) use ($room) {
                                    $q->where('room_id', $room->id);
                                });
                            })
                            ->count();
                        
                        if ($roomMarks >= $allocations) {
                            $completedRooms++;
                        }
                    }
                }
                
                return [
                    'id' => $session->id,
                    'date' => $session->session_date,
                    'hostel_id' => $session->hostel_id,
                    'status' => strtolower($session->status),
                    'total_rooms' => $totalRooms,
                    'completed_rooms' => $completedRooms,
                    'attendance_percentage' => $completionPercent,
                    'counts' => $counts,
                ];
            });
        
        return response()->json(['data' => $sessions]);
    }

    public function show(Request $request, AttendanceSessionV2 $session): JsonResponse
    {
        $user = $request->user();
        
        // Check authorization
        $hostelIds = HostelScope::idsFor($user);
        if (!in_array($session->hostel_id, $hostelIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $counts = $this->calculateSessionCounts($session);
        
        return response()->json([
            'data' => [
                'id' => $session->id,
                'hostel_id' => $session->hostel_id,
                'date' => $session->session_date,
                'window' => $session->metadata['window'] ?? [
                    'start' => $session->scheduled_at?->toISOString(),
                    'end' => $session->scheduled_at?->addHours(2)->toISOString(),
                ],
                'status' => strtolower($session->status),
                'counts' => $counts,
            ]
        ]);
    }

    private function calculateSessionCounts(AttendanceSessionV2 $session): array
    {
        $logs = AttendanceLog::where('session_id', $session->id)->get();
        
        // Get total students in hostel for this session
        $totalStudents = \App\Models\RoomBed::whereHas('room', function ($query) use ($session) {
            $query->where('hostel_id', $session->hostel_id);
        })->count();
        
        $present = $logs->where('status', 'present')->count();
        $absent = $logs->where('status', 'absent')->count();
        $marked = $present + $absent;
        
        return [
            'total' => $totalStudents,
            'present' => $present,
            'absent' => $absent,
            'unmarked' => max(0, $totalStudents - $marked),
        ];
    }

    private function calculateRoomCounts(AttendanceSessionV2 $session, int $roomId): array
    {
        $allocations = \App\Models\RoomAllocation::whereHas('roomBed', function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })
            ->where('is_active', true)
            ->with('student')
            ->get();
            
        $students = $allocations->pluck('student')->filter();
        
        $logs = AttendanceLog::where('session_id', $session->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get();

        return [
            'present' => $logs->where('status', 'present')->count(),
            'absent' => $logs->where('status', 'absent')->count(),
            'unmarked' => $students->count() - $logs->count(),
        ];
    }

    private function maskUid(string $uid): string
    {
        if (strlen($uid) <= 4) {
            return str_repeat('*', strlen($uid));
        }
        
        return substr($uid, 0, 4) . str_repeat('*', strlen($uid) - 4);
    }

    private function isStudentOnLeave(int $studentId, string $date): bool
    {
        // Check if student has active outpass for the date
        $hasOutpass = \App\Models\Domain\OutPass\OutPass::where('student_id', $studentId)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->exists();

        // TODO: Add leave system integration when available
        // $hasLeave = \App\Models\Leave::where('student_id', $studentId)
        //     ->where('status', 'approved')
        //     ->whereDate('from_date', '<=', $date)
        //     ->whereDate('to_date', '>=', $date)
        //     ->exists();

        return $hasOutpass; // || $hasLeave;
    }
}
