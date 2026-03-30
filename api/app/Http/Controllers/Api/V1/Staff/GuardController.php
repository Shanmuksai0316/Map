<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Support\HostelScope;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Student;
use App\Models\User;
use App\Enums\OutPassStatus;
use App\Domain\Leaves\Models\Leave;
use App\Domain\GuestEntries\Models\GuestEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard Controller
 * 
 * Handles Guard-specific operations: emergency exits, gate entries, visitor management
 */
class GuardController extends Controller
{
    /**
     * Active outpasses for Guard gate workflow (mobile app).
     * GET /api/v1/guard/outpasses/active
     */
    public function activeOutpasses(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            Log::info('Guard activeOutpasses: no tenant_id', ['user_id' => $user->id]);
            return response()->json(['data' => []], Response::HTTP_OK);
        }
        $hostelIds = HostelScope::idsFor($user);
        if (empty($hostelIds)) {
            Log::info('Guard activeOutpasses: no hostels in scope', ['user_id' => $user->id, 'tenant_id' => $tenantId]);
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $nowIst = Carbon::now('Asia/Kolkata');

        $outpasses = OutPass::query()
            ->with([
                'student.user',
                'student.roomAllocations.roomBed.room',
            ])
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->where('status', OutPassStatus::APPROVED)
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', $nowIst)
            ->whereNull('actual_out_time')
            ->latest()
            ->limit(200)
            ->get()
            ->map(function (OutPass $p) use ($nowIst) {
                $student = $p->student;
                $roomNumber = null;
                if ($student) {
                    $activeAllocation = $student->roomAllocations?->firstWhere('is_active', true);
                    $roomNumber = $activeAllocation?->roomBed?->room?->display_name;
                }

                $requestedAt = $p->requested_at ? Carbon::parse($p->requested_at)->setTimezone('Asia/Kolkata') : null;
                $validUntil = $p->valid_until ? Carbon::parse($p->valid_until)->setTimezone('Asia/Kolkata') : null;

                return [
                    'id' => (int) $p->id,
                    'student_name' => $student?->user?->name ?? 'Unknown',
                    'student_id' => $student?->student_uid ?? null,
                    'room_number' => $roomNumber,
                    'reason' => (string) ($p->reason instanceof \BackedEnum ? $p->reason->value : ($p->reason ?? '')),
                    'status' => (string) ($p->status instanceof \BackedEnum ? $p->status->value : ($p->status ?? '')),
                    'out_date' => $requestedAt?->toDateString() ?? $nowIst->toDateString(),
                    'out_time' => $requestedAt?->format('H:i') ?? $nowIst->format('H:i'),
                    'expected_in_date' => $validUntil?->toDateString() ?? $nowIst->toDateString(),
                    'expected_in_time' => $validUntil?->format('H:i') ?? $nowIst->format('H:i'),
                ];
            })
            ->values();

        return response()->json(['data' => $outpasses], Response::HTTP_OK);
    }

    /**
     * Single outpass by id for Guard (e.g. after QR/verify to show detail).
     * GET /api/.../guard/outpasses/{id}
     */
    public function showOutpass(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            return response()->json(['error' => 'tenant_required'], Response::HTTP_FORBIDDEN);
        }
        $hostelIds = HostelScope::idsFor($user);
        if (empty($hostelIds)) {
            return response()->json(['error' => 'no_hostels'], Response::HTTP_FORBIDDEN);
        }

        $p = OutPass::query()
            ->with(['student.user', 'student.roomAllocations.roomBed.room'])
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->find($id);

        if (!$p) {
            return response()->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $student = $p->student;
        $roomNumber = null;
        if ($student) {
            $activeAllocation = $student->roomAllocations?->firstWhere('is_active', true);
            $roomNumber = $activeAllocation?->roomBed?->room?->display_name;
        }
        $nowIst = Carbon::now('Asia/Kolkata');
        $requestedAt = $p->requested_at ? Carbon::parse($p->requested_at)->setTimezone('Asia/Kolkata') : null;
        $validUntil = $p->valid_until ? Carbon::parse($p->valid_until)->setTimezone('Asia/Kolkata') : null;

        $data = [
            'id' => (int) $p->id,
            'student_name' => $student?->user?->name ?? 'Unknown',
            'student_id' => $student?->student_uid ?? null,
            'room_number' => $roomNumber,
            'reason' => (string) ($p->reason instanceof \BackedEnum ? $p->reason->value : ($p->reason ?? '')),
            'status' => (string) ($p->status instanceof \BackedEnum ? $p->status->value : ($p->status ?? '')),
            'out_date' => $requestedAt?->toDateString() ?? $nowIst->toDateString(),
            'out_time' => $requestedAt?->format('H:i') ?? $nowIst->format('H:i'),
            'expected_in_date' => $validUntil?->toDateString() ?? $nowIst->toDateString(),
            'expected_in_time' => $validUntil?->format('H:i') ?? $nowIst->format('H:i'),
            'qr_scanned_at' => $p->qr_scanned_at?->toIso8601String(),
            'backup_code_used_at' => $p->backup_code_used_at?->toIso8601String(),
        ];

        return response()->json(['data' => $data], Response::HTTP_OK);
    }

    /**
     * Active leaves for Guard gate workflow (mobile app).
     * GET /api/v1/guard/leaves/active
     *
     * Note: Guard needs ONLY "exit time" recording for Leave.
     */
    public function activeLeaves(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            Log::info('Guard activeLeaves: no tenant_id', ['user_id' => $user->id]);
            return response()->json(['data' => []], Response::HTTP_OK);
        }
        $hostelIds = HostelScope::idsFor($user);
        if (empty($hostelIds)) {
            Log::info('Guard activeLeaves: no hostels in scope', ['user_id' => $user->id, 'tenant_id' => $tenantId]);
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $todayIst = Carbon::now('Asia/Kolkata')->toDateString();

        $leaves = Leave::query()
            ->with([
                'student.user',
                'student.roomAllocations.roomBed.room',
            ])
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $todayIst)
            ->whereDate('to_date', '>=', $todayIst)
            ->whereNull('actual_departure_time')
            ->latest()
            ->limit(200)
            ->get()
            ->map(function (Leave $l) {
                $student = $l->student;
                $roomNumber = null;
                if ($student) {
                    $activeAllocation = $student->roomAllocations?->firstWhere('is_active', true);
                    $roomNumber = $activeAllocation?->roomBed?->room?->display_name;
                }

                return [
                    'id' => (int) $l->id,
                    'student_name' => $student?->user?->name ?? 'Unknown',
                    'student_id' => $student?->student_uid ?? null,
                    'room_number' => $roomNumber,
                    'leave_type' => (string) ($l->title ?? 'Leave'),
                    'status' => (string) ($l->status ?? 'approved'),
                    'from_date' => $l->from_date?->format('Y-m-d'),
                    'to_date' => $l->to_date?->format('Y-m-d'),
                    // Used by the guard UI to decide whether to record exit time
                    'actual_departure_time' => $l->actual_departure_time?->toIso8601String(),
                    'emergency_contact' => $l->emergency_contact,
                    // Backward compat for older UI fields
                    'time' => $l->actual_departure_time?->format('H:i') ?? '',
                ];
            })
            ->values();

        return response()->json(['data' => $leaves], Response::HTTP_OK);
    }

    /**
     * Active guest entries for Guard gate workflow (mobile app).
     * GET /api/v1/guard/guest-entries/active
     */
    public function activeGuestEntries(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            Log::info('Guard activeGuestEntries: no tenant_id', ['user_id' => $user->id]);
            return response()->json(['data' => []], Response::HTTP_OK);
        }
        $hostelIds = HostelScope::idsFor($user);
        if (empty($hostelIds)) {
            Log::info('Guard activeGuestEntries: no hostels in scope', ['user_id' => $user->id, 'tenant_id' => $tenantId]);
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $todayIst = Carbon::now('Asia/Kolkata')->toDateString();

        $entries = GuestEntry::query()
            ->with([
                'student.user',
                'student.roomAllocations.roomBed.room',
            ])
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('check_in_time')->orWhere('check_in_time', '');
            })
            ->whereDate('visit_date', $todayIst)
            ->latest()
            ->limit(200)
            ->get()
            ->map(function (GuestEntry $g) {
                $student = $g->student;
                $roomNumber = null;
                if ($student) {
                    $activeAllocation = $student->roomAllocations?->firstWhere('is_active', true);
                    $roomNumber = $activeAllocation?->roomBed?->room?->display_name;
                }

                $firstGuest = null;
                if (is_array($g->guests) && !empty($g->guests)) {
                    $firstGuest = $g->guests[0] ?? null;
                }

                $guestsArr = is_array($g->guests) ? $g->guests : [];
                return [
                    'id' => (int) $g->id,
                    'student_name' => $student?->user?->name ?? 'Unknown',
                    'student_id' => $student?->student_uid ?? null,
                    'room_number' => $roomNumber,
                    'number_of_guests' => count($guestsArr),
                    'visit_date' => $g->visit_date?->format('Y-m-d'),
                    'visitor_name' => is_array($firstGuest) ? ($firstGuest['name'] ?? '') : '',
                    'status' => (string) ($g->status ?? 'approved'),
                    'time' => trim(($g->visit_date?->format('Y-m-d') ?? '') . ' ' . ($g->check_in_time ?? '')),
                    'reason' => $g->purpose_to_visit,
                    'guest_relationship' => is_array($firstGuest) ? ($firstGuest['relationship'] ?? null) : null,
                    'guest_phone' => is_array($firstGuest) ? ($firstGuest['phone'] ?? null) : null,
                ];
            })
            ->values();

        return response()->json(['data' => $entries], Response::HTTP_OK);
    }

    /**
     * Completed guest entries for Guard history (mobile app).
     * GET /api/v1/guard/guest-entries/completed
     */
    public function completedGuestEntries(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            return response()->json(['data' => []], Response::HTTP_OK);
        }
        $hostelIds = HostelScope::idsFor($user);
        if (empty($hostelIds)) {
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $entries = GuestEntry::query()
            ->with([
                'student.user',
                'student.roomAllocations.roomBed.room',
            ])
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->whereNotNull('check_in_time')
            ->where('check_in_time', '!=', '')
            ->latest('check_in_time')
            ->limit($request->integer('per_page', 100))
            ->get()
            ->map(function (GuestEntry $g) {
                $student = $g->student;
                $roomNumber = null;
                if ($student) {
                    $activeAllocation = $student->roomAllocations?->firstWhere('is_active', true);
                    $roomNumber = $activeAllocation?->roomBed?->room?->display_name;
                }
                $guestsArr = is_array($g->guests) ? $g->guests : [];
                $firstGuest = $guestsArr[0] ?? null;
                return [
                    'id' => (int) $g->id,
                    'student_name' => $student?->user?->name ?? 'Unknown',
                    'student_id' => $student?->student_uid ?? null,
                    'room_number' => $roomNumber,
                    'number_of_guests' => count($guestsArr),
                    'visit_date' => $g->visit_date?->format('Y-m-d'),
                    'status' => 'completed',
                    'check_in_time' => $g->check_in_time,
                ];
            })
            ->values();

        return response()->json(['data' => $entries], Response::HTTP_OK);
    }

    /**
     * Mark guest entry as entered (guard records entry time).
     * POST /api/v1/guard/guest-entries/{id}/mark-entry
     */
    public function markGuestEntry(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            return response()->json(['error' => 'tenant_required'], Response::HTTP_FORBIDDEN);
        }
        $hostelIds = HostelScope::idsFor($user);
        if (empty($hostelIds)) {
            return response()->json(['error' => 'no_hostels'], Response::HTTP_FORBIDDEN);
        }

        $entry = GuestEntry::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->find($id);

        if (!$entry) {
            return response()->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }
        if (strtolower((string) $entry->status) !== 'approved') {
            return response()->json(['error' => 'not_approved', 'message' => 'Guest entry is not approved'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!empty($entry->check_in_time)) {
            return response()->json(['error' => 'already_marked', 'message' => 'Entry already marked'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $now = Carbon::now('Asia/Kolkata');
        $entry->update([
            'check_in_time' => $now->format('H:i'),
            'status' => 'completed',
        ]);

        return response()->json([
            'message' => 'Entry marked successfully',
            'data' => [
                'id' => $entry->id,
                'check_in_time' => $entry->check_in_time,
                'status' => $entry->status,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Create emergency exit for a student
     * 
     * POST /api/v1/gate/emergency-exit
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function emergencyExit(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'note' => 'required|string|min:10|max:500',
            'hostel_id' => 'nullable|integer|exists:hostels,id',
        ]);

        try {
            DB::beginTransaction();

            // Get student details
            $student = Student::with('user', 'hostel')->findOrFail($validated['student_id']);

            // Create OutPass with emergency_exit status
            $outpass = OutPass::create([
                'tenant_id' => $user->tenant_id,
                'student_id' => $student->id,
                'hostel_id' => $validated['hostel_id'] ?? $student->hostel_id,
                'reason' => 'EMERGENCY EXIT: ' . $validated['note'],
                'overnight' => false,
                'status' => 'emergency_exit', // Use string
                'requested_at' => now(),
                'valid_until' => now()->addHours(24),
                'created_by_user_id' => $user->id,
            ]);

            // Create gate entry record
            DB::table('gate_entries')->insert([
                'tenant_id' => $user->tenant_id,
                'student_id' => $student->id,
                'outpass_id' => $outpass->id,
                'hostel_id' => $validated['hostel_id'] ?? $student->hostel_id,
                'direction' => 'out',
                'out_time' => now(),
                'recorded_by_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create incident for emergency exit
            \App\Models\Incident::create([
                'tenant_id' => $user->tenant_id,
                'type' => 'emergency_exit',
                'student_id' => $student->id,
                'note' => $validated['note'],
                'status' => 'open',
                'opened_by' => $user->id,
                'opened_at' => now(),
            ]);

            // Notify Rector
            $rectors = User::role('Rector')
                ->where('tenant_id', $user->tenant_id)
                ->get();

            foreach ($rectors as $rector) {
                // Send notification (email/push)
                // TODO: Implement notification sending
                Log::info('Emergency exit notification sent to Rector', [
                    'rector_id' => $rector->id,
                    'student_id' => $student->id,
                    'outpass_id' => $outpass->id,
                ]);
            }

            DB::commit();

            Log::info('Emergency exit recorded', [
                'guard_id' => $user->id,
                'student_id' => $student->id,
                'outpass_id' => $outpass->id,
                'note' => $validated['note'],
            ]);

            return response()->json([
                'message' => 'Emergency exit recorded successfully',
                'data' => [
                    'id' => $outpass->id,
                    'student_name' => $student->user->name,
                    'student_id' => $student->id,
                    'status' => $outpass->status->value,
                    'out_time' => now()->toIso8601String(),
                    'valid_until' => $outpass->valid_until->toIso8601String(),
                    'note' => $validated['note'],
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Emergency exit failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'student_id' => $validated['student_id'],
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/emergency_exit_failed',
                'title' => 'Emergency Exit Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to record emergency exit. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get active gate passes for today
     * 
     * GET /api/v1/gate/passes/active
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getActivePasses(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $passes = OutPass::with(['student.user', 'hostel'])
                ->where('tenant_id', $user->tenant_id)
                ->whereIn('status', [OutPassStatus::APPROVED, OutPassStatus::EMERGENCY_EXIT])
                ->whereDate('requested_at', '<=', now())
                ->whereDate('valid_until', '>=', now())
                ->latest()
                ->get()
                ->map(function ($pass) {
                    return [
                        'id' => $pass->id,
                        'student_id' => $pass->student_id,
                        'student_name' => $pass->student->user->name,
                        'hostel_name' => $pass->hostel->name ?? 'N/A',
                        'reason' => $pass->reason,
                        'status' => $pass->status->value,
                        'out_time' => $pass->requested_at?->toIso8601String(),
                        'expected_in' => $pass->valid_until?->toIso8601String(),
                        'overnight' => $pass->overnight,
                    ];
                });

            return response()->json([
                'data' => $passes,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch active passes', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/passes_fetch_failed',
                'title' => 'Passes Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve active passes.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get recent gate entries
     * 
     * GET /api/v1/gate/entries/recent
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecentEntries(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 20);

        try {
            $entries = DB::table('gate_entries')
                ->join('students', 'gate_entries.student_id', '=', 'students.id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('hostels', 'gate_entries.hostel_id', '=', 'hostels.id')
                ->where('gate_entries.tenant_id', $user->tenant_id)
                ->select(
                    'gate_entries.id',
                    'gate_entries.direction',
                    'gate_entries.out_time',
                    'gate_entries.in_time',
                    'gate_entries.created_at',
                    'students.id as student_id',
                    'users.name as student_name',
                    'hostels.name as hostel_name'
                )
                ->orderBy('gate_entries.created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'student_id' => $entry->student_id,
                        'student_name' => $entry->student_name,
                        'hostel_name' => $entry->hostel_name ?? 'N/A',
                        'direction' => $entry->direction,
                        'out_time' => $entry->out_time,
                        'in_time' => $entry->in_time,
                        'timestamp' => $entry->created_at,
                    ];
                });

            return response()->json([
                'data' => $entries,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch recent entries', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/entries_fetch_failed',
                'title' => 'Entries Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve recent entries.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get dashboard statistics for Guard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            return response()->json([
                'data' => [
                    'total_verifications_today' => 0,
                    'check_outs_today' => 0,
                    'check_ins_today' => 0,
                    'active_outpasses' => 0,
                    'pending_guest_entries' => 0,
                    'emergency_exits_today' => 0,
                ],
            ], Response::HTTP_OK);
        }

        try {
            // Today's verification breakdown
            $todayStats = DB::table('gate_entries')
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->selectRaw("
                    COUNT(*) as total_verifications,
                    SUM(CASE WHEN direction = 'out' THEN 1 ELSE 0 END) as check_outs,
                    SUM(CASE WHEN direction = 'in' THEN 1 ELSE 0 END) as check_ins
                ")
                ->first();

            // Active outpasses
            $activeOutpasses = OutPass::where('tenant_id', $tenantId)
                ->whereIn('status', [OutPassStatus::APPROVED, OutPassStatus::EMERGENCY_EXIT])
                ->whereDate('valid_until', '>=', today())
                ->count();

            // Pending guest entries today
            $pendingGuests = DB::table('guest_entries')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereDate('visit_date', today())
                ->whereNull('actual_arrival_time')
                ->count();

            // Emergency exits today
            $emergencyExits = OutPass::where('tenant_id', $tenantId)
                ->where('status', OutPassStatus::EMERGENCY_EXIT)
                ->whereDate('created_at', today())
                ->count();

            return response()->json([
                'data' => [
                    'total_verifications_today' => $todayStats->total_verifications ?? 0,
                    'check_outs_today' => $todayStats->check_outs ?? 0,
                    'check_ins_today' => $todayStats->check_ins ?? 0,
                    'active_outpasses' => $activeOutpasses,
                    'pending_guest_entries' => $pendingGuests,
                    'emergency_exits_today' => $emergencyExits,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch guard dashboard stats', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/stats_fetch_failed',
                'title' => 'Stats Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve dashboard statistics.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get guard history (combined)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $type = $request->input('type', 'all'); // all, leave, outpass, guest_entry
        $date = $request->date('date');

        try {
            $entries = DB::table('gate_entries')
                ->join('students', 'gate_entries.student_id', '=', 'students.id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('hostels', 'gate_entries.hostel_id', '=', 'hostels.id')
                ->where('gate_entries.tenant_id', $tenantId)
                ->where('gate_entries.recorded_by_user_id', $user->id)
                ->when($date, fn ($q) => $q->whereDate('gate_entries.created_at', $date))
                ->when($type === 'leave', fn ($q) => $q->whereNotNull('gate_entries.leave_id'))
                ->when($type === 'outpass', fn ($q) => $q->whereNotNull('gate_entries.outpass_id')->whereNull('gate_entries.leave_id'))
                ->select(
                    'gate_entries.id',
                    'gate_entries.direction',
                    'gate_entries.out_time',
                    'gate_entries.in_time',
                    'gate_entries.outpass_id',
                    'gate_entries.leave_id',
                    'gate_entries.created_at',
                    'students.id as student_id',
                    'users.name as student_name',
                    'hostels.name as hostel_name'
                )
                ->orderBy('gate_entries.created_at', 'desc')
                ->paginate($request->integer('per_page', 20));

            return response()->json([
                'data' => collect($entries->items())->map(fn ($entry) => [
                    'id' => $entry->id,
                    'leave_id' => $entry->leave_id,
                    'outpass_id' => $entry->outpass_id,
                    'student_id' => $entry->student_id,
                    'student_name' => $entry->student_name,
                    'hostel_name' => $entry->hostel_name ?? 'N/A',
                    'direction' => $entry->direction,
                    'type' => $entry->leave_id ? 'leave' : ($entry->outpass_id ? 'outpass' : 'other'),
                    'out_time' => $entry->out_time,
                    'in_time' => $entry->in_time,
                    'timestamp' => $entry->created_at,
                ]),
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch guard history', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'Failed to retrieve history',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get leave verification history
     */
    public function leaveHistory(Request $request): JsonResponse
    {
        $request->merge(['type' => 'leave']);
        return $this->history($request);
    }

    /**
     * Get outpass verification history
     */
    public function outpassHistory(Request $request): JsonResponse
    {
        $request->merge(['type' => 'outpass']);
        return $this->history($request);
    }

    /**
     * Get guest entry history
     */
    public function guestEntryHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        try {
            $entries = DB::table('guest_visits')
                ->join('guest_entries', 'guest_visits.guest_entry_id', '=', 'guest_entries.id')
                ->join('students', 'guest_entries.student_id', '=', 'students.id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('guest_visits.tenant_id', $tenantId)
                ->where('guest_visits.verified_by', $user->id)
                ->select(
                    'guest_visits.id',
                    'guest_entries.guest_name',
                    'guest_entries.guest_relation',
                    'guest_visits.check_in_time',
                    'guest_visits.check_out_time',
                    'guest_visits.created_at',
                    'users.name as student_name'
                )
                ->orderBy('guest_visits.created_at', 'desc')
                ->paginate($request->integer('per_page', 20));

            return response()->json([
                'data' => collect($entries->items())->map(fn ($entry) => [
                    'id' => $entry->id,
                    'guest_name' => $entry->guest_name,
                    'guest_relation' => $entry->guest_relation,
                    'student_name' => $entry->student_name,
                    'check_in_time' => $entry->check_in_time,
                    'check_out_time' => $entry->check_out_time,
                    'timestamp' => $entry->created_at,
                ]),
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch guest entry history', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'Failed to retrieve guest entry history',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
