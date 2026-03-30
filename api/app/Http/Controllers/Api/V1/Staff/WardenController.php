<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Leaves\Models\Leave;
use App\Domain\SickLeaves\Models\SickLeave;
use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Repositories\ChecklistInstanceRepository;
use App\Services\Checklists\ChecklistInstanceSyncService;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Services\FeatureFlagsService;
use App\Support\HostelScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\OutPassStatus;
use App\Enums\OutPassType;

class WardenController extends Controller
{
    /**
     * Get rooms under warden's hostel
     */
    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Get warden's assigned hostel(s)
            $hostelIds = $user->staffHostels()
                ->pluck('hostels.id')
                ->toArray();

            Log::info('Warden rooms debug', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'hostel_ids' => $hostelIds,
                'hostel_count' => count($hostelIds)
            ]);

            if (empty($hostelIds)) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hostels assigned to this warden',
                ], Response::HTTP_OK);
            }

            // Get tenant_id for additional filtering if Room model has tenant_id
            $tenantId = $user->tenant_id;

            $rooms = Room::whereIn('hostel_id', $hostelIds)
                ->when($tenantId && \Schema::hasColumn('rooms', 'tenant_id'), function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })
                ->with(['hostel'])
                ->orderBy('floor_code')
                ->orderBy('number')
                ->get()
                ->map(function ($room) {
                    return [
                        'id' => (string) $room->id,
                        'room_no' => $room->number,
                        'floor' => $room->floor_code ?? 'N/A',
                        'capacity' => $room->capacity,
                        'occupied' => $room->occupied_beds_count ?? 0,
                        'hostel_id' => (string) $room->hostel_id,
                        'hostel_name' => $room->hostel?->name,
                    ];
                });

            return response()->json([
                'data' => $rooms,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch warden rooms', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve rooms. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get students for a specific room.
     * Optional query param: date (Y-m-d). When provided, returns on_leave and existing attendance for that date.
     */
    public function roomStudents(Request $request, $roomId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $hostelIds = $user->staffHostels()
                ->pluck('hostels.id')
                ->toArray();

            if (empty($hostelIds)) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hostels assigned to this warden',
                ], Response::HTTP_OK);
            }

            $room = Room::where('id', $roomId)
                ->whereIn('hostel_id', $hostelIds)
                ->firstOrFail();

            $roomBedIds = RoomBed::where('room_id', $roomId)->pluck('id')->toArray();

            if (empty($roomBedIds)) {
                return response()->json([
                    'data' => [],
                    'room' => [
                        'id' => (string) $room->id,
                        'room_no' => $room->number,
                        'floor' => $room->floor_code ?? 'N/A',
                        'hostel_name' => $room->hostel?->name,
                    ],
                ], Response::HTTP_OK);
            }

            $targetDate = $request->query('date');
            $dateObj = $targetDate ? Carbon::parse($targetDate, 'Asia/Kolkata')->startOfDay() : Carbon::now('Asia/Kolkata')->startOfDay();
            $dateStr = $dateObj->toDateString();

            $allocations = RoomAllocation::with(['student.user', 'student.hostel', 'roomBed.room'])
                ->whereIn('room_bed_id', $roomBedIds)
                ->where('is_active', true)
                ->get();

            // Only include allocations with a valid student (skip orphaned allocations)
            $allocations = $allocations->filter(fn ($alloc) => $alloc->student !== null);

            $studentIds = $allocations->pluck('student_id')->unique()->values()->toArray();

            if (empty($studentIds)) {
                return response()->json([
                    'data' => [],
                    'room' => [
                        'id' => (string) $room->id,
                        'room_no' => $room->number,
                        'floor' => $room->floor_code ?? 'N/A',
                        'hostel_name' => $room->hostel?->name,
                    ],
                ], Response::HTTP_OK);
            }

            // Students on approved leave for the target date (Leave: from_date <= date <= to_date)
            $onLeaveStudentIds = Leave::query()
                ->where('tenant_id', $room->tenant_id)
                ->whereIn('student_id', $studentIds)
                ->where('status', 'approved')
                ->whereDate('from_date', '<=', $dateStr)
                ->whereDate('to_date', '>=', $dateStr)
                ->pluck('student_id')
                ->unique()
                ->toArray();

            // Optional: approved sick leave (if SickLeave had date range we could filter by date; for now skip or use submitted_at)
            // Leaving SickLeave out of "on_leave" for a specific date unless we add from_date/to_date.

            // Session for target date (night_check) – may not exist for past dates
            $session = AttendanceSession::query()
                ->where('tenant_id', $room->tenant_id)
                ->where('hostel_id', $room->hostel_id)
                ->where('kind', 'night_check')
                ->whereDate('session_date', $dateStr)
                ->first();

            $marksByStudent = [];
            if ($session) {
                $marks = AttendanceMark::query()
                    ->where('attendance_session_id', $session->id)
                    ->whereIn('student_id', $studentIds)
                    ->get();
                foreach ($marks as $mark) {
                    $marksByStudent[(int) $mark->student_id] = [
                        'status' => $mark->status,
                        'notes' => $mark->notes ?? $mark->note ?? null,
                    ];
                }
            }

            $students = $allocations->map(function ($alloc) use ($onLeaveStudentIds, $marksByStudent) {
                $student = $alloc->student;
                $sid = (int) $student->id;
                $onLeave = in_array($sid, $onLeaveStudentIds, true);
                $mark = $marksByStudent[$sid] ?? null;
                return [
                    'id' => (int) $student->id,
                    'map_student_id' => $student->map_student_id,
                    'name' => $student->user?->name,
                    'phone' => $student->user?->phone,
                    'email' => $student->user?->email,
                    'roll_no' => $student->roll_no,
                    'program' => $student->program,
                    'year_of_study' => $student->year_of_study,
                    'hostel_name' => $student->hostel?->name,
                    'room_no' => $alloc->roomBed?->room?->number,
                    'bed_no' => $alloc->roomBed?->code ?? $alloc->bed_no,
                    'on_leave' => $onLeave,
                    'attendance_status' => $mark ? $mark['status'] : null,
                    'attendance_notes' => $mark ? $mark['notes'] : null,
                ];
            })->values();

            Log::info('Warden roomStudents', [
                'room_id' => $roomId,
                'date' => $dateStr,
                'student_count' => $students->count(),
                'on_leave_count' => count($onLeaveStudentIds),
            ]);

            return response()->json([
                'data' => $students,
                'room' => [
                    'id' => (string) $room->id,
                    'room_no' => $room->number,
                    'floor' => $room->floor_code ?? 'N/A',
                    'hostel_name' => $room->hostel?->name,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch room students', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'room_id' => $roomId,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve room students. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function ensureNightCheckSession(Room $room, ?string $attendanceDate = null): AttendanceSession
    {
        $hostel = Hostel::findOrFail($room->hostel_id);

        $sessionDateIst = $attendanceDate
            ? Carbon::parse($attendanceDate, 'Asia/Kolkata')->startOfDay()
            : Carbon::now('Asia/Kolkata')->startOfDay();
        $nowIst = Carbon::now('Asia/Kolkata');

        $curfewTime = Carbon::createFromFormat('H:i:s', $hostel->curfew_time ?? '22:00:00', 'Asia/Kolkata');
        $openAt = $sessionDateIst->copy()->setTime($curfewTime->hour, $curfewTime->minute)->subHour();
        $closeAt = $sessionDateIst->copy()->setTime($curfewTime->hour, $curfewTime->minute)->addHours(2);

        if ($closeAt->lessThan($openAt)) {
            $closeAt->addDay();
        }

        $status = $nowIst->between($openAt, $closeAt) ? AttendanceSession::STATUS_OPEN : AttendanceSession::STATUS_SCHEDULED;

        return AttendanceSession::query()->updateOrCreate(
            [
                'tenant_id' => $room->tenant_id,
                'hostel_id' => $room->hostel_id,
                'kind' => 'night_check',
                'session_date' => $sessionDateIst->toDateString(),
            ],
            [
                'campus_id' => $hostel->campus_id,
                'name' => "Night Check - {$hostel->name}",
                'scheduled_at' => $sessionDateIst,
                'status' => $status,
                'metadata' => [
                    'open_at' => $openAt->toISOString(),
                    'close_at' => $closeAt->toISOString(),
                    'session_date' => $sessionDateIst->toDateString(),
                ],
                'session_date' => $sessionDateIst->toDateString(),
            ]
        );
    }

    public function submitAttendance(Request $request, $roomId): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can submit attendance.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $room = Room::findOrFail($roomId);
            $hostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();
            if (!in_array($room->hostel_id, $hostelIds, true)) {
                return response()->json(['error' => 'Room not assigned to this warden'], 403);
            }

            $validated = $request->validate([
                'date' => ['required', 'date'],
                'attendance' => ['required', 'array', 'min:1'],
                'attendance.*.student_id' => ['required', 'numeric', 'exists:students,id'],
                'attendance.*.status' => ['required', 'string', 'in:P,A,L'],
                'attendance.*.comments' => ['nullable', 'string', 'max:255'],
            ]);

            $session = $this->ensureNightCheckSession($room, $validated['date'] ?? null);
            $statusMap = [
                'P' => AttendanceMark::STATUS_PRESENT,
                'A' => AttendanceMark::STATUS_ABSENT,
                'L' => AttendanceMark::STATUS_LEAVE,
            ];
            $attendanceDate = Carbon::parse($validated['date'])->toDateString();

            DB::transaction(function () use ($session, $room, $validated, $attendanceDate, $statusMap, $user): void {
                $roomBedIds = RoomBed::where('room_id', $room->id)->pluck('id')->toArray();

                foreach ($validated['attendance'] as $entry) {
                    $studentId = (int) $entry['student_id'];
                    $statusKey = strtoupper($entry['status']);
                    $status = $statusMap[$statusKey] ?? AttendanceMark::STATUS_UNMARKED;
                    $comment = $entry['comments'] ?? null;

                    $allocation = RoomAllocation::query()
                        ->where('tenant_id', $session->tenant_id)
                        ->where('student_id', $studentId)
                        ->where('is_active', true)
                        ->whereIn('room_bed_id', $roomBedIds)
                        ->first();

                    if (!$allocation) {
                        continue;
                    }

                    AttendanceMark::query()->updateOrCreate(
                        [
                            'attendance_session_id' => $session->id,
                            'student_id' => $allocation->student_id,
                        ],
                        [
                            'tenant_id' => $session->tenant_id,
                            'hostel_id' => $room->hostel_id,
                            'attendance_date' => $attendanceDate,
                            'status' => $status,
                            'notes' => $status === AttendanceMark::STATUS_ABSENT || $status === AttendanceMark::STATUS_LEAVE
                                ? ($comment ?? null)
                                : null,
                            'marked_by' => $user->id,
                            'marked_at' => now(),
                        ]
                    );
                }

                $session->recomputeCounts();
            });

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('Warden submitAttendance failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'room_id' => $roomId,
                'user_id' => $user->id ?? null,
            ]);
            $message = config('app.debug')
                ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                : 'Failed to submit attendance. Please try again.';
            return response()->json([
                'message' => $message,
                'error' => 'submit_failed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get students under warden's hostel
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Get warden's assigned hostel(s)
            $hostelIds = $user->staffHostels()
                ->pluck('hostels.id')
                ->toArray();

            if (empty($hostelIds)) {
                return response()->json([
                    'data' => [],
                ], Response::HTTP_OK);
            }

            $students = Student::where('tenant_id', $user->tenant_id)
                ->whereIn('hostel_id', $hostelIds)
                ->with(['user', 'hostel', 'roomAllocations.roomBed.room'])
                ->get()
                ->map(function ($student) {
                    $activeAlloc = $student->roomAllocations->where('is_active', true)->first();
                    $roomNumber = $activeAlloc?->roomBed?->room?->number ?? 'N/A';
                    $bedCode = $activeAlloc?->roomBed?->code ?? 'N/A';
                    return [
                        'id' => (int) $student->id,
                        'map_student_id' => $student->map_student_id,
                        'name' => $student->user?->name,
                        'phone' => $student->user?->phone,
                        'email' => $student->user?->email,
                        'roll_no' => $student->roll_no,
                        'program' => $student->program,
                        'year_of_study' => $student->year_of_study,
                        'hostel_name' => $student->hostel?->name,
                        'room_no' => $roomNumber,
                        'bed_no' => $bedCode,
                    ];
                });

            return response()->json([
                'data' => $students,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch warden students', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve students. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search students by room number or phone (for parcel flow).
     * Query params: room_no (optional), phone (optional). At least one required.
     */
    public function parcelStudentsSearch(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        $roomNo = $request->query('room_no');
        $phone = $request->query('phone');

        if (! $roomNo && ! $phone) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation',
                'title' => 'Validation Error',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Provide room_no or phone to search.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $hostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();

            $query = Student::where('tenant_id', $user->tenant_id)
                ->with(['user', 'hostel', 'roomAllocations.roomBed.room']);

            if (! empty($hostelIds)) {
                $query->whereIn('hostel_id', $hostelIds);
            }

            if ($phone !== null && $phone !== '') {
                $query->whereHas('user', function ($q) use ($phone) {
                    $q->where('phone', 'like', '%' . preg_replace('/\D/', '', $phone) . '%');
                });
            }

            if ($roomNo !== null && $roomNo !== '') {
                $query->whereHas('roomAllocations', function ($q) use ($roomNo) {
                    $q->where('is_active', true)
                        ->whereHas('roomBed.room', function ($r) use ($roomNo) {
                            $r->where('number', 'like', '%' . $roomNo . '%');
                        });
                });
            }

            $students = $query->get()->map(function ($student) {
                $activeAlloc = $student->roomAllocations->where('is_active', true)->first();
                $roomNumber = $activeAlloc?->roomBed?->room?->number ?? 'N/A';
                $bedCode = $activeAlloc?->roomBed?->code ?? 'N/A';
                return [
                    'id' => (string) $student->id,
                    'map_student_id' => $student->map_student_id,
                    'name' => $student->user?->name,
                    'phone' => $student->user?->phone,
                    'hostel_id' => (int) $student->hostel_id,
                    'hostel_name' => $student->hostel?->name,
                    'room_no' => $roomNumber,
                    'bed_no' => $bedCode,
                ];
            });

            return response()->json(['data' => $students->values()->all()], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Parcel student search failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to search students. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get single student detail
     */
    public function studentDetail(Request $request, $studentId): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $hostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();
            if (empty($hostelIds)) {
                return response()->json(['data' => null], Response::HTTP_OK);
            }

            $student = Student::where('tenant_id', $user->tenant_id)
                ->where('id', $studentId)
                ->whereIn('hostel_id', $hostelIds)
                ->with(['user', 'hostel', 'roomAllocations.roomBed.room'])
                ->first();

            if (!$student) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => 'Student not found or not under your hostels.',
                ], Response::HTTP_NOT_FOUND);
            }

            $activeAlloc = $student->roomAllocations->where('is_active', true)->first();
            $roomNumber = $activeAlloc?->roomBed?->room?->number ?? $student->room_no;
            $bedCode = $activeAlloc?->roomBed?->code ?? null;
            $roomCapacity = $activeAlloc?->roomBed?->room?->capacity;

            $data = [
                'id' => (string) $student->id,
                'map_student_id' => $student->map_student_id,
                'name' => $student->user?->name,
                'email' => $student->user?->email,
                'phone' => $student->user?->phone,
                'gender' => $student->gender,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'roll_no' => $student->roll_no,
                'erp_number' => $student->erp_number,
                'program' => $student->program,
                'department' => $student->department,
                'year_of_study' => $student->year_of_study,
                'hostel_name' => $student->hostel?->name,
                'room_no' => $roomNumber,
                'room_capacity' => $roomCapacity,
                'current_status' => $student->current_status,
                'father_name' => $student->father_name,
                'father_phone' => $student->father_phone,
                'mother_name' => $student->mother_name,
                'mother_phone' => $student->mother_phone,
                'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone,
                'guardian_relationship' => $student->guardian_relationship,
                'guardian_address' => $student->guardian_address,
                'blood_group' => $student->blood_group,
                'medical_conditions' => $student->medical_conditions,
                'allergies' => $student->allergies,
                'emergency_contact_name' => $student->emergency_contact_name,
                'emergency_contact_relationship' => $student->emergency_contact_relationship,
                'emergency_contact_phone' => $student->emergency_contact_phone,
                'emergency_contact_address' => $student->emergency_contact_address,
            ];

            return response()->json(['data' => $data], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to fetch student detail', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'student_id' => $studentId,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve student detail. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get requests/tickets assigned to warden
     */
    public function requests(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $today = $request->boolean('today', false);

            // Get warden's assigned hostel(s)
            $hostelIds = $user->staffHostels()
                ->pluck('hostels.id')
                ->toArray();

            $limit = $request->integer('limit', 50);
            $allRequests = collect();

            // 1. Fetch tickets (housekeeping, repair_maintenance, guest_entry)
            $ticketQuery = Ticket::where('tenant_id', $user->tenant_id)
                ->whereIn('hostel_id', $hostelIds)
                ->with(['hostel', 'reporterStudent.user']);

            if ($today) {
                $ticketQuery->whereDate('created_at', today());
            }

            $tickets = $ticketQuery->latest()
                ->limit($limit)
                ->get()
                ->map(function ($ticket) {
                    return [
                        'id' => 'ticket_' . $ticket->id,
                        'title' => $ticket->title,
                        'description' => $ticket->description,
                        'category' => $ticket->category,
                        'priority' => $ticket->priority,
                        'status' => $ticket->status,
                        'hostel_name' => $ticket->hostel?->name,
                        'reporter_name' => $ticket->reporterStudent?->user?->name ?? $ticket->reporterUser?->name,
                        'created_at' => $ticket->created_at->toIso8601String(),
                        'request_type' => 'ticket',
                    ];
                });

            $allRequests = $allRequests->merge($tickets);

            // 2. Fetch leave requests
            $leaveQuery = Leave::where('tenant_id', $user->tenant_id)
                ->whereIn('hostel_id', $hostelIds)
                ->with(['student.user', 'hostel']);

            if ($today) {
                $leaveQuery->whereDate('created_at', today());
            }

            $leaves = $leaveQuery->latest()
                ->limit($limit)
                ->get()
                ->map(function ($leave) {
                    return [
                        'id' => 'leave_' . $leave->id,
                        'title' => $leave->title,
                        'description' => $leave->description,
                        'category' => 'leave',
                        'priority' => 'medium',
                        'status' => $leave->status,
                        'hostel_name' => $leave->hostel?->name,
                        'reporter_name' => $leave->student?->user?->name,
                        'created_at' => $leave->created_at->toIso8601String(),
                        'request_type' => 'leave',
                    ];
                });

            $allRequests = $allRequests->merge($leaves);

            // 3. Fetch outpass requests
            $outpassQuery = OutPass::where('tenant_id', $user->tenant_id)
                ->whereIn('hostel_id', $hostelIds)
                ->with(['student.user', 'hostel']);

            if ($today) {
                $outpassQuery->whereDate('created_at', today());
            }

            $outpasses = $outpassQuery->latest()
                ->limit($limit)
                ->get()
                ->map(function ($outpass) {
                    $reasonValue = $outpass->reason instanceof OutPassType
                        ? $outpass->reason->value
                        : (string) ($outpass->reason ?? 'normal');
                    $statusValue = $outpass->status instanceof OutPassStatus
                        ? $outpass->status->value
                        : (string) $outpass->status;
                    $description = $outpass->note ?: $reasonValue;
                    if ($outpass->overnight) {
                        $description .= ' (Overnight)';
                    }

                    return [
                        'id' => 'outpass_' . $outpass->id,
                        'title' => 'Out Pass Request',
                        'description' => $description,
                        'category' => 'outpass',
                        'priority' => 'medium',
                        'status' => $statusValue,
                        'hostel_name' => $outpass->hostel?->name,
                        'reporter_name' => $outpass->student?->user?->name,
                        'created_at' => $outpass->created_at->toIso8601String(),
                        'request_type' => 'outpass',
                    ];
                });

            $allRequests = $allRequests->merge($outpasses);

            // Sort all requests by created_at and limit
            $sortedRequests = $allRequests
                ->sortByDesc(function ($request) {
                    return $request['created_at'];
                })
                ->take($limit)
                ->values();

            return response()->json([
                'data' => $sortedRequests,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch warden requests', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve requests. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get warden's daily checklist
     *
     * Returns the warden's assigned checklist for today with all items.
     * Uses the same checklist system as other staff roles.
     */
    public function checklist(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $user->tenant_id) {
            return response()->json([
                'data' => [],
                'message' => 'No tenant assigned. Please contact your administrator.',
            ], Response::HTTP_OK);
        }

        // Check if checklist feature is enabled
        $enabled = app(FeatureFlagsService::class)->enabled('checklists_module', $user->tenant_id);
        if (!$enabled) {
            return response()->json([
                'data' => [],
                'message' => 'Checklist feature is not enabled for this tenant.',
            ], Response::HTTP_OK);
        }

        try {
            $instances = $this->getOrCreateDailyInstances($request);
            if ($instances->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No checklist assigned for today.',
                ], Response::HTTP_OK);
            }

            // Format response to match mobile app expectations
            $formattedInstances = $instances->map(function (ChecklistInstance $instance) {
                return [
                    'id' => $instance->id,
                    'template_id' => $instance->template_id,
                    'date' => $instance->date,
                    'shift' => $instance->shift,
                    'role' => $instance->role,
                    'status' => $instance->status,
                    'review_status' => $instance->review_status,
                    'total_tasks' => $instance->total_tasks,
                    'completed_tasks' => $instance->completed_tasks,
                    'submitted_at' => $instance->submitted_at,
                    'reviewed_at' => $instance->reviewed_at,
                    'manager_note' => $instance->manager_note,
                    'items' => $instance->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'code' => $item->code,
                            'label' => $item->label,
                            'state' => $item->state,
                            'comment' => $item->comment,
                            'photo_urls' => $item->photo_urls,
                            'completed_at' => $item->completed_at,
                            'require_photo' => (bool) ($item->require_photo ?? false),
                            'require_comment' => (bool) ($item->require_comment ?? false),
                        ];
                    })->values(),
                ];
            });

            return response()->json([
                'data' => $formattedInstances,
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error('Failed to fetch warden checklist', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
            ]);

            $detail = 'Failed to retrieve checklist. Please try again.';
            if (config('app.debug')) {
                $detail = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            }

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => $detail,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Toggle a checklist item for the warden (by item id).
     */
    public function toggleChecklistItem(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'is_completed' => 'required|boolean',
            'comment' => 'nullable|string|max:500',
        ]);

        $instances = $this->getOrCreateDailyInstances($request);
        if ($instances->isEmpty()) {
            return response()->json([
                'error' => 'no_instance',
                'message' => 'No checklist assigned for today.',
            ], Response::HTTP_NOT_FOUND);
        }

        $instance = $instances->first();
        $item = $instance->items()->where('id', $itemId)->first();
        if (! $item) {
            return response()->json([
                'error' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $isCompleted = (bool) $validated['is_completed'];

        if ($isCompleted) {
            $incomingComment = $validated['comment'] ?? null;
            $existingPhotos = $item->photo_urls ?? [];
            $hasPhoto = ! empty($existingPhotos);
            $hasComment = ! empty(trim((string) ($incomingComment ?? $item->comment ?? '')));

            if (($item->require_photo ?? false) && ! $hasPhoto) {
                return response()->json([
                    'error' => 'photo_required',
                    'message' => 'Photo is required to complete this task',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (($item->require_comment ?? false) && ! $hasComment) {
                return response()->json([
                    'error' => 'comment_required',
                    'message' => 'Comment is required to complete this task',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $item->forceFill([
            'state' => $isCompleted ? 'Done' : 'Pending',
            'comment' => $validated['comment'] ?? $item->comment,
            'completed_at' => $isCompleted ? now() : null,
        ])->save();

        $instance->recalcCompleted();

        return response()->json([
            'message' => 'Task updated',
            'data' => [
                'task_id' => $item->id,
                'completed_at' => $item->completed_at,
                'is_completed' => $isCompleted,
            ],
        ]);
    }

    /**
     * Upload a photo for a checklist item (by item id).
     */
    public function uploadChecklistPhoto(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $instances = $this->getOrCreateDailyInstances($request);
        if ($instances->isEmpty()) {
            return response()->json([
                'error' => 'no_instance',
                'message' => 'No checklist assigned for today.',
            ], Response::HTTP_NOT_FOUND);
        }

        $instance = $instances->first();
        $item = $instance->items()->where('id', $itemId)->first();
        if (! $item) {
            return response()->json([
                'error' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $path = $request->file('photo')->store(
            "checklists/{$instance->tenant_id}/{$instance->id}",
            'public'
        );

        $photoUrl = Storage::url($path);
        $photos = $item->photo_urls ?? [];
        $photos[] = $photoUrl;

        $item->forceFill([
            'photo_urls' => $photos,
        ])->save();

        return response()->json([
            'photo_url' => $photoUrl,
            'data' => [
                'task_id' => $item->id,
                'photo_url' => $photoUrl,
            ],
        ]);
    }

    /**
     * Submit the checklist for the day (creates a snapshot).
     */
    public function submitChecklist(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        return DB::transaction(function () use ($request, $user) {
            $instances = $this->getOrCreateDailyInstances($request);
            if ($instances->isEmpty()) {
                return response()->json([
                    'error' => 'no_instance',
                    'message' => 'No checklist assigned for today.',
                ], Response::HTTP_NOT_FOUND);
            }

            $persistent = $instances->first()->load(['items', 'template']);
            $items = $persistent->items()->orderBy('id')->get();
            $total = $items->count();
            $completed = $items->where('state', 'Done')->count();

            if ($total === 0) {
                return response()->json([
                    'error' => 'no_tasks',
                    'message' => 'No checklist fields configured',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($completed < $total) {
                return response()->json([
                    'error' => 'incomplete',
                    'message' => 'Please complete all fields before submitting',
                    'data' => [
                        'completed' => $completed,
                        'total' => $total,
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $nowIst = Carbon::now('Asia/Kolkata');

            $submission = ChecklistInstance::query()->create([
                'tenant_id' => $user->tenant_id,
                'template_id' => $persistent->template_id,
                'date' => $nowIst->toDateString(),
                'shift' => 'Submission',
                'role' => 'Warden',
                'assignee_user_id' => $user->id,
                'status' => 'Submitted',
                'review_status' => 'Pending',
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'submitted_at' => $nowIst,
                'completed_at' => $nowIst,
            ]);

            $rows = [];
            foreach ($items as $item) {
                $rows[] = [
                    'tenant_id' => $user->tenant_id,
                    'instance_id' => $submission->id,
                    'code' => $item->code,
                    'label' => $item->label,
                    'require_photo' => (bool) ($item->require_photo ?? false),
                    'require_comment' => (bool) ($item->require_comment ?? false),
                    'state' => $item->state,
                    'comment' => $item->comment,
                    'photo_urls' => $item->photo_urls,
                    'completed_at' => $item->completed_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                ChecklistItem::query()->insert($rows);
            }

            $persistent->items()->update([
                'state' => 'Pending',
                'comment' => null,
                'photo_urls' => null,
                'completed_at' => null,
                'updated_at' => now(),
            ]);
            $persistent->forceFill([
                'status' => 'Pending',
                'completed_tasks' => 0,
                'submitted_at' => null,
                'completed_at' => null,
            ])->save();

            return response()->json([
                'message' => 'Checklist submitted',
                'data' => [
                    'submission_id' => $submission->id,
                    'submitted_at' => $submission->submitted_at,
                ],
            ]);
        }, 3);
    }

    /**
     * Get or create today's checklist instance(s) for the warden.
     *
     * @return \Illuminate\Support\Collection<int, ChecklistInstance>
     */
    private function getOrCreateDailyInstances(Request $request)
    {
        $user = $request->user();
        if (! $user->tenant_id) {
            return collect();
        }
        $today = now()->timezone('Asia/Kolkata')->toDateString();

        $instances = ChecklistInstance::query()
            ->with(['items', 'template'])
            ->where('tenant_id', $user->tenant_id)
            ->where('assignee_user_id', $user->id)
            ->where('role', 'Warden')
            ->whereDate('date', $today)
            ->get();

        $syncService = app(ChecklistInstanceSyncService::class);

        if ($instances->isEmpty()) {
            $template = ChecklistTemplate::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('role', 'Warden')
                ->where('active', true)
                ->latest('id')
                ->first();

            if ($template) {
                $repository = app(ChecklistInstanceRepository::class);
                $tasks = $syncService->normalizeTasks(is_array($template->tasks) ? $template->tasks : []);
                $todayIst = Carbon::now('Asia/Kolkata')->startOfDay();

                $instance = $repository->firstOrCreateDaily(
                    tenantId: $user->tenant_id,
                    templateId: (int) $template->id,
                    role: 'Warden',
                    assigneeUserId: (int) $user->id,
                    dateIst: $todayIst,
                    shift: 'Daily',
                    tasks: $tasks,
                );

                $syncService->syncInstanceItemsFromTemplate($instance, $tasks);
                $instances = collect([$instance->load(['items', 'template'])]);
            }
        }

        if ($instances->isEmpty()) {
            return $instances;
        }

        foreach ($instances as $instance) {
            if ($instance->template) {
                $tasks = $syncService->normalizeTasks(is_array($instance->template->tasks) ? $instance->template->tasks : []);
                $syncService->syncInstanceItemsFromTemplate($instance, $tasks);
            }
        }
        $instances->load('items');

        return $instances;
    }
}
