<?php

namespace App\Http\Controllers\Api\V1\CampusManager;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Hostel;
use App\Models\Ticket;
use App\Models\Domain\OutPass\OutPass;
use App\Domain\Leaves\Models\Leave;
use App\Domain\GuestEntries\Models\GuestEntry;
use App\Models\LaundryRequest;
use App\Models\FacilityBooking;
use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff Controller for Campus Manager
 * 
 * Manages staff overview, requests aggregation, and dashboard statistics
 */
class StaffController extends Controller
{
    /**
     * Staff roles shown in Campus Manager "My Staff" and "Staff Checklist" views.
     *
     * These are onboarding-assigned operational roles only.
     */
    private function operationalRoles(): array
    {
        return [
            'Warden',
            'Guard',
            'HK Supervisor',
            'RM Supervisor',
            'Laundry Manager',
            'Sports Manager',
        ];
    }

    /**
     * Resolve hostels Campus Manager can manage for staff views.
     *
     * Primary source is the manager's active staff assignments. For legacy tenants
     * where manager-hostel mapping is missing, fall back to all tenant hostels.
     */
    private function resolveManagedHostelIds(Request $request, User $actor)
    {
        $managedHostelIds = $actor->staffAssignments()->pluck('hostels.id');

        if ($managedHostelIds->isEmpty()) {
            $managedHostelIds = Hostel::query()
                ->where('tenant_id', $actor->tenant_id)
                ->pluck('id');
        }

        $hostelId = $request->integer('hostel_id');
        $hostelName = trim((string) $request->query('hostel_name', ''));

        return Hostel::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('id', $managedHostelIds->all())
            ->when($hostelId, fn ($query) => $query->where('id', $hostelId))
            ->when(
                $hostelName !== '',
                fn ($query) => $query->whereRaw('LOWER(hostels.name) LIKE ?', ['%' . strtolower($hostelName) . '%'])
            )
            ->pluck('id');
    }

    /**
     * Get list of all staff under this campus
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $managedHostelIds = $this->resolveManagedHostelIds($request, $user);

        if ($managedHostelIds->isEmpty()) {
            return response()->json(['data' => []]);
        }
        
        $staff = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('kind', '!=', 'student')
            ->where(function ($query) {
                $query->where('archived', false)->orWhereNull('archived');
            })
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['Warden', 'Guard', 'HK Supervisor', 'RM Supervisor', 'Sports Manager', 'Laundry Manager']);
            })
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'Super Admin');
            })
            ->whereHas('staffAssignments', function ($query) use ($managedHostelIds) {
                $query->whereIn('hostels.id', $managedHostelIds->all());
            })
            ->with([
                'roles',
                'staffAssignments' => function ($query) use ($managedHostelIds) {
                    $query->whereIn('hostels.id', $managedHostelIds->all());
                },
            ])
            ->get()
            ->map(fn ($staff) => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'employee_id' => $staff->employee_id,
                'role' => $staff->roles->first()?->name,
                'assigned_hostels' => $staff->staffAssignments->map(fn (Hostel $hostel) => [
                    'id' => $hostel->id,
                    'name' => $hostel->name,
                ])->values(),
                'is_active' => $staff->is_active,
                'last_active_at' => $staff->last_active_at,
            ]);

        return response()->json(['data' => $staff]);
    }

    /**
     * Get staff details
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        
        if ($user->tenant_id !== $authUser->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $user->load(['roles', 'staffAssignments']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'employee_id' => $user->employee_id,
                'role' => $user->roles->first()?->name,
                'assigned_hostels' => $user->staffAssignments->map(fn (Hostel $hostel) => [
                    'id' => $hostel->id,
                    'name' => $hostel->name,
                ])->values(),
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'last_active_at' => $user->last_active_at,
            ],
        ]);
    }

    /**
     * Get staff by hostel
     */
    public function byHostel(Request $request, Hostel $hostel): JsonResponse
    {
        $user = $request->user();
        
        if ($hostel->tenant_id !== $user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $staff = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('staffAssignments', fn ($q) => $q->where('hostels.id', $hostel->id))
            ->with(['roles'])
            ->get()
            ->map(fn ($staff) => [
                'id' => $staff->id,
                'name' => $staff->name,
                'role' => $staff->roles->first()?->name,
                'phone' => $staff->phone,
            ]);

        return response()->json(['data' => $staff]);
    }

    /**
     * Get dashboard statistics for Campus Manager
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // hostels table has no is_active column; count all hostels for tenant
        $activeHostels = Hostel::where('tenant_id', $tenantId)
            ->count();

        $residentStudents = DB::table('students')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('hostel_id')
            ->count();

        $openRequests = Ticket::where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress', 'pending'])
            ->count();

        // tickets table has no resolved_at column; use updated_at instead
        $completedRequestsToday = Ticket::where('tenant_id', $tenantId)
            ->where('status', 'resolved')
            ->whereDate('updated_at', today())
            ->count();

        return response()->json([
            'data' => [
                'active_hostels' => $activeHostels,
                'resident_students' => $residentStudents,
                'open_requests' => $openRequests,
                'completed_requests_today' => $completedRequestsToday,
            ],
        ]);
    }

    /**
     * Get housekeeping requests (view-only)
     */
    public function housekeepingRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $requests = Ticket::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('category', ['cleaning', 'housekeeping'])
            ->with(['student.user', 'hostel', 'assignee'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $requests->map(fn ($ticket) => [
                'id' => $ticket->id,
                'student_name' => $ticket->student?->user?->name,
                'room' => $ticket->room_number,
                'hostel' => $ticket->hostel?->name,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'assigned_to' => $ticket->assignee?->name,
                'created_at' => $ticket->created_at,
                'sla_deadline' => $ticket->sla_deadline,
                'is_delayed' => $ticket->isDelayed(),
            ]),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get maintenance requests (view-only)
     */
    public function maintenanceRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $requests = Ticket::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('category', ['maintenance', 'repair_maintenance', 'room_maintenance', 'electrical', 'plumbing', 'furniture'])
            ->with(['student.user', 'hostel', 'assignee'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $requests->map(fn ($ticket) => [
                'id' => $ticket->id,
                'student_name' => $ticket->student?->user?->name,
                'room' => $ticket->room_number,
                'hostel' => $ticket->hostel?->name,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'assigned_to' => $ticket->assignee?->name,
                'created_at' => $ticket->created_at,
                'sla_deadline' => $ticket->sla_deadline,
                'is_delayed' => $ticket->isDelayed(),
            ]),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get outpass requests (view-only)
     */
    public function outpassRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $requests = OutPass::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['student.user', 'hostel'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $requests->map(function ($outpass) {
                // Convert status enum to string
                $statusValue = $outpass->status instanceof \App\Enums\OutPassStatus 
                    ? $outpass->status->value 
                    : (string) $outpass->status;
                
                // Convert reason enum to string if needed
                $reasonValue = $outpass->reason instanceof \App\Enums\OutPassType
                    ? $outpass->reason->value
                    : (string) ($outpass->reason ?? 'normal');
                
                return [
                    'id' => (string) $outpass->id,
                    'unique_id' => $outpass->unique_id ?? "OP-{$outpass->id}",
                    'student_name' => $outpass->student?->user?->name,
                    'hostel' => $outpass->hostel?->name,
                    'reason' => $reasonValue,
                    'status' => $statusValue,
                    'requested_at' => $outpass->requested_at?->toIso8601String(),
                    'valid_until' => $outpass->valid_until?->toIso8601String(),
                    'overnight' => $outpass->overnight ?? false,
                    'created_at' => $outpass->created_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get leave requests (view-only)
     */
    public function leaveRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $requests = Leave::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['student.user', 'hostel'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $requests->map(fn ($leave) => [
                'id' => (string) $leave->id,
                'unique_id' => $leave->unique_id ?? "LEV-{$leave->id}",
                'student_name' => $leave->student?->user?->name,
                'hostel' => $leave->hostel?->name,
                'reason_for_leave' => $leave->reason_for_leave,
                'title' => $leave->title,
                'description' => $leave->description,
                'status' => $leave->status,
                'from_date' => $leave->from_date?->format('Y-m-d'),
                'to_date' => $leave->to_date?->format('Y-m-d'),
                'submitted_at' => $leave->submitted_at?->format('Y-m-d H:i:s'),
                'created_at' => $leave->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get guest entry requests (view-only)
     */
    public function guestEntryRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $requests = GuestEntry::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['student.user', 'hostel'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $requests->map(function ($entry) {
                // Extract first guest info from guests array
                $guests = $entry->guests ?? [];
                $firstGuest = !empty($guests) && is_array($guests) ? $guests[0] : null;
                
                return [
                    'id' => (string) $entry->id,
                    'unique_id' => $entry->unique_id ?? "GST-{$entry->id}",
                    'student_name' => $entry->student?->user?->name,
                    'hostel' => $entry->hostel?->name,
                    'guests' => $guests,
                    'guest_name' => $firstGuest['name'] ?? null,
                    'guest_relation' => $firstGuest['relationship'] ?? null,
                    'purpose_to_visit' => $entry->purpose_to_visit,
                    'primary_contact_mobile' => $entry->primary_contact_mobile,
                    'status' => $entry->status,
                    'visit_date' => $entry->visit_date?->format('Y-m-d'),
                    'check_in_time' => $entry->check_in_time,
                    'check_out_time' => $entry->check_out_time,
                    'submitted_at' => $entry->submitted_at?->format('Y-m-d H:i:s'),
                    'created_at' => $entry->created_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get sports requests (view-only)
     */
    public function sportsRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $requests = FacilityBooking::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['facility', 'user'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $requests->map(fn ($booking) => [
                'id' => $booking->id,
                'user_name' => $booking->user?->name,
                'facility' => $booking->facility?->name,
                'facility_type' => $booking->facility?->type,
                'status' => $booking->status,
                'booking_date' => $booking->booking_date,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
            ]),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get laundry requests (view-only)
     */
    public function laundryRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $requests = LaundryRequest::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['student.user', 'hostel'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $requests->map(fn ($laundry) => [
                'id' => $laundry->id,
                'student_name' => $laundry->student?->user?->name,
                'hostel' => $laundry->hostel?->name,
                'room' => $laundry->room_number,
                'item_count' => $laundry->item_count,
                'weight_kg' => $laundry->weight_kg,
                'status' => $laundry->status,
                'created_at' => $laundry->created_at,
                'sla_deadline' => $laundry->sla_deadline,
                'is_delayed' => $laundry->isDelayed(),
            ]),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get staff checklist summary for assigned staff.
     *
     * Returns staff list based on active hostel assignments (onboarding assignments)
     * and overlays checklist progress metrics. Staff with no checklist records are
     * included with zero values.
     */
    public function staffChecklistSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $legacyDateFilterRequested = trim((string) $request->query('date', '')) !== '';
        $allowedRoles = $this->operationalRoles();
        $managedHostelIds = $this->resolveManagedHostelIds($request, $user);

        if ($managedHostelIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'summary' => [
                    'total_staff' => 0,
                    'total_checklists' => 0,
                    'total_submitted' => 0,
                    'total_approved' => 0,
                    'total_pending' => 0,
                    'overall_submission_rate' => 0,
                ],
            ]);
        }

        $staff = User::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', '!=', 'student')
            ->where(function ($query) {
                $query->where('archived', false)->orWhereNull('archived');
            })
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $allowedRoles))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->whereHas('staffAssignments', function ($query) use ($managedHostelIds) {
                $query->whereIn('hostels.id', $managedHostelIds->all());
            })
            ->with(['roles:id,name'])
            ->get(['users.id', 'users.name']);

        $aggregate = DB::table('checklist_instances as ci')
            ->where('ci.tenant_id', $tenantId)
            ->when(
                $staff->isNotEmpty(),
                fn ($query) => $query->whereIn('ci.assignee_user_id', $staff->pluck('id')->all())
            )
            ->select(
                'ci.assignee_user_id as user_id',
                DB::raw('COUNT(*) as total_checklists'),
                DB::raw("SUM(CASE WHEN ci.status = 'Submitted' OR ci.review_status = 'Approved' THEN 1 ELSE 0 END) as submitted_count"),
                DB::raw("SUM(CASE WHEN ci.review_status = 'Approved' THEN 1 ELSE 0 END) as approved_count"),
                DB::raw("SUM(CASE WHEN ci.status = 'Pending' THEN 1 ELSE 0 END) as pending_count"),
                DB::raw("SUM(ci.completed_tasks) as total_completed_tasks"),
                DB::raw("SUM(ci.total_tasks) as total_task_items"),
                DB::raw('MAX(ci.submitted_at) as last_submitted_at'),
                DB::raw('MAX(ci.date) as last_checklist_date')
            )
            ->groupBy('ci.assignee_user_id')
            ->get()
            ->keyBy('user_id');

        $summary = $staff
            ->map(function (User $staffMember) use ($aggregate, $legacyDateFilterRequested) {
                $stats = $aggregate->get($staffMember->id);
                $totalChecklists = (int) ($stats->total_checklists ?? 0);
                $submittedCount = (int) ($stats->submitted_count ?? 0);
                $approvedCount = (int) ($stats->approved_count ?? 0);
                $pendingCount = (int) ($stats->pending_count ?? 0);
                $totalCompletedTasks = (int) ($stats->total_completed_tasks ?? 0);
                $totalTaskItems = (int) ($stats->total_task_items ?? 0);
                $taskCompletionPercentage = $totalTaskItems > 0
                    ? round(($totalCompletedTasks / $totalTaskItems) * 100)
                    : 0;

                return [
                    'user_id' => $staffMember->id,
                    'user_name' => $staffMember->name,
                    'role' => $staffMember->roles->first()?->name,
                    'total_checklists' => $totalChecklists,
                    'submitted_count' => $submittedCount,
                    'approved_count' => $approvedCount,
                    'pending_count' => $pendingCount,
                    'total_completed_tasks' => $totalCompletedTasks,
                    'total_task_items' => $totalTaskItems,
                    'task_completion_percentage' => $taskCompletionPercentage,
                    'last_submitted_at' => $stats->last_submitted_at ?? null,
                    'last_checklist_date' => $stats->last_checklist_date ?? null,
                    // Backward-compatible aliases for older mobile builds.
                    'completed_tasks' => $totalCompletedTasks,
                    'total_tasks' => $totalTaskItems,
                    // Older screen hard-filters completion_percentage === 100.
                    // When legacy date filter is sent (?date=today|yesterday),
                    // force visibility of assigned staff while keeping accurate
                    // percentages in task_completion_percentage for new clients.
                    'completion_percentage' => $legacyDateFilterRequested ? 100 : $taskCompletionPercentage,
                    'checklist_submission_percentage' => $totalChecklists > 0
                        ? round(($submittedCount / $totalChecklists) * 100)
                        : 0,
                ];
            })
            ->sortBy('user_name')
            ->values();

        // Get overall stats
        $overallStats = [
            'total_staff' => $summary->unique('user_id')->count(),
            'total_checklists' => $summary->sum('total_checklists'),
            'total_submitted' => $summary->sum('submitted_count'),
            'total_approved' => $summary->sum('approved_count'),
            'total_pending' => $summary->sum('pending_count'),
            'overall_submission_rate' => $summary->sum('total_checklists') > 0
                ? round(($summary->sum('submitted_count') / $summary->sum('total_checklists')) * 100)
                : 0,
        ];

        return response()->json([
            'data' => $summary,
            'summary' => $overallStats,
        ]);
    }

    /**
     * Get detailed checklist tasks for a specific staff member and date.
     *
     * Used by Campus Manager mobile app when tapping a staff member in the
     * "Staff Checklist" tab. Returns the same task shape as MyChecklistController::current().
     *
     * Query param:
     * - date=today|yesterday|YYYY-MM-DD (optional)
     * If date is omitted, latest checklist instance is returned.
     */
    public function staffChecklistDetail(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $tenantId = $actor->tenant_id;

        if (! $tenantId || $user->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $now = Carbon::now('Asia/Kolkata');
        $dateParam = trim((string) $request->query('date', ''));
        $targetDate = null;
        if ($dateParam !== '') {
            if ($dateParam === 'yesterday') {
                $targetDate = $now->copy()->subDay()->toDateString();
            } elseif ($dateParam === 'today') {
                $targetDate = $now->toDateString();
            } else {
                // Allow explicit YYYY-MM-DD fallback; invalid values default to today.
                try {
                    $targetDate = Carbon::parse($dateParam, 'Asia/Kolkata')->toDateString();
                } catch (\Throwable $e) {
                    $targetDate = $now->toDateString();
                }
            }
        }

        $instanceBaseQuery = ChecklistInstance::query()
            ->with(['items', 'template'])
            ->where('tenant_id', $tenantId)
            ->where('assignee_user_id', $user->id);

        $instance = null;

        // Respect explicit date when present, but fall back to latest history
        // so older mobile UIs with today/yesterday filters still show data.
        if ($targetDate) {
            $instance = (clone $instanceBaseQuery)
                ->whereDate('date', $targetDate)
                ->orderByDesc('id')
                ->first();
        }

        if (! $instance) {
            // Prefer submitted/completed history first.
            $instance = (clone $instanceBaseQuery)
                ->where(function ($query) {
                    $query->whereNotNull('submitted_at')
                        ->orWhere('completed_tasks', '>', 0);
                })
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();
        }

        if (! $instance) {
            $instance = (clone $instanceBaseQuery)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();
        }

        if (! $instance) {
            $payload = [
                'checklist_date' => $targetDate,
                'submitted_at' => null,
                'tasks' => [],
            ];

            return response()->json([
                'data' => $payload,
                ...$payload,
            ]);
        }

        $tasks = $instance->items()
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (ChecklistItem $item, int $index) {
                $photoUrls = $item->photo_urls ?? [];

                return [
                    'index' => $index,
                    'id' => $index,
                    'title' => $item->label,
                    'description' => null,
                    'requires_photo' => (bool) ($item->require_photo ?? false),
                    'requires_comment' => (bool) ($item->require_comment ?? false),
                    'completed' => $item->state === 'Done',
                    'is_completed' => $item->state === 'Done',
                    'completed_at' => $item->completed_at,
                    'photo_url' => $photoUrls[0] ?? null,
                    'comment' => $item->comment,
                ];
            })
            ->all();

        $payload = [
            'checklist_date' => $instance->date?->toDateString() ?? $targetDate,
            'submitted_at' => $instance->submitted_at,
            'tasks' => $tasks,
        ];

        return response()->json([
            'data' => $payload,
            ...$payload,
        ]);
    }

    /**
     * Get detailed checklist compliance report
     *
     * Returns checklist data for a date range with filtering options
     */
    public function checklistComplianceReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'role' => ['nullable', 'string'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $fromDate = $validated['from_date'] ?? now()->subDays(7)->toDateString();
        $toDate = $validated['to_date'] ?? now()->toDateString();

        $query = DB::table('checklist_instances as ci')
            ->join('users as u', 'ci.assignee_user_id', '=', 'u.id')
            ->leftJoin('checklist_templates as ct', 'ci.template_id', '=', 'ct.id')
            ->where('ci.tenant_id', $tenantId)
            ->whereBetween('ci.date', [$fromDate, $toDate]);

        if (!empty($validated['role'])) {
            $query->where('ci.role', $validated['role']);
        }

        if (!empty($validated['staff_id'])) {
            $query->where('ci.assignee_user_id', $validated['staff_id']);
        }

        $report = $query->select(
            'ci.id',
            'ci.date',
            'ci.role',
            'u.id as user_id',
            'u.name as user_name',
            'ct.title as template_title',
            'ci.status',
            'ci.review_status',
            'ci.total_tasks',
            'ci.completed_tasks',
            'ci.submitted_at',
            'ci.reviewed_at'
        )
            ->orderBy('ci.date', 'desc')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'date' => $row->date,
                'role' => $row->role,
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'template_title' => $row->template_title,
                'status' => $row->status,
                'review_status' => $row->review_status,
                'total_tasks' => (int) $row->total_tasks,
                'completed_tasks' => (int) $row->completed_tasks,
                'task_completion_percentage' => $row->total_tasks > 0
                    ? round(($row->completed_tasks / $row->total_tasks) * 100)
                    : 0,
                'submitted_at' => $row->submitted_at,
                'reviewed_at' => $row->reviewed_at,
                'is_overdue' => $row->status === 'Pending',
            ]);

        return response()->json([
            'data' => $report,
            'meta' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'total_records' => $report->count(),
            ],
        ]);
    }
}
