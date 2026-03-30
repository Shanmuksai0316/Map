<?php

namespace App\Support\Attendance;

use App\Domain\Attendance\Models\AttendanceRoomSubmission;
use App\Domain\Attendance\Models\AttendanceSession;
use Illuminate\Support\Collection;

class RoomRosterQuery
{
    public static function roomSummaries(AttendanceSession $session): Collection
    {
        return \DB::table('room_allocations as ra')
            ->join('room_beds as rb', 'rb.id', '=', 'ra.room_bed_id')
            ->join('rooms as r', 'r.id', '=', 'rb.room_id')
            ->join('students as st', 'st.id', '=', 'ra.student_id')
            ->leftJoin('attendance_logs as am', function ($j) use ($session) {
                $j->on('am.student_id', '=', 'st.id')
                  ->where('am.attendance_session_id', '=', $session->id);
            })
            ->where('ra.is_active', 1)
            ->where('r.hostel_id', $session->hostel_id)
            ->where('ra.tenant_id', $session->tenant_id)
            ->leftJoin('attendance_room_submissions as ars', function ($j) use ($session) {
                $j->on('ars.room_id', '=', 'r.id')
                  ->where('ars.attendance_session_id', '=', $session->id);
            })
            ->select([
                'r.id as room_id',
                'r.number as room_number',
                \DB::raw('COUNT(DISTINCT st.id) as total_students'),
                \DB::raw('COUNT(CASE WHEN am.status = "present" THEN 1 END) as present_count'),
                \DB::raw('COUNT(CASE WHEN am.status = "absent" THEN 1 END) as absent_count'),
                \DB::raw('COUNT(CASE WHEN am.status IS NULL THEN 1 END) as unmarked_count'),
                'ars.submitted_at as submitted_at',
                'ars.submitted_by as submitted_by',
            ])
            ->groupBy('r.id', 'r.number', 'ars.submitted_at', 'ars.submitted_by')
            ->orderBy('r.number')
            ->get()
            ->map(function ($room) {
                $leaveCount = 0; // TODO: Calculate leave count from OutPasses
                
                return [
                    'room_id' => $room->room_id,
                    'room_number' => $room->room_number,
                    'total_students' => $room->total_students,
                    'present_count' => $room->present_count,
                    'absent_count' => $room->absent_count,
                    'unmarked_count' => $room->unmarked_count,
                    'leave_count' => $leaveCount,
                    'submitted_at' => $room->submitted_at,
                    'submitted_by' => $room->submitted_by,
                ];
            });
    }

    public static function roomRoster(AttendanceSession $session, int $roomId): Collection
    {
        $leaveStudentIds = app(\App\Services\Attendance\LeaveDeriver::class)
            ->leaveStudentIds($session);

        return \DB::table('room_allocations as ra')
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
                'u.id as student_id',
                'u.name as student_name',
                \DB::raw('COALESCE(am.status, "unmarked") as current_status'),
                'am.note as comment',
            ])
            ->get()
            ->map(function ($student) use ($leaveStudentIds) {
                $isLeave = $leaveStudentIds->contains($student->student_id);
                $currentStatus = $isLeave ? 'leave' : $student->current_status;
                $locked = $isLeave;

                return [
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name,
                    'current_status' => $currentStatus,
                    'locked' => $locked,
                    'comment' => $student->comment,
                ];
            });
    }
}


