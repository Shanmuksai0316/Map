<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OutPassStatus;
use App\Http\Controllers\Controller;
use App\Models\Domain\OutPass\OutPass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RectorDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Verify Rector role
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access this dashboard.',
            ], Response::HTTP_FORBIDDEN);
        }
        
        $tenantId = $user->tenant_id;
        
        if (!$tenantId) {
            \Log::error('RectorDashboardController::dashboard: No tenant_id for user', ['user_id' => $user->id]);
            return response()->json([
                'data' => [
                    'pending_approvals' => 0,
                    'approvals_last_7d' => 0,
                    'late_returns' => 0,
                    'incidents_last_7d' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
        
        // Pending approvals (recent - last 7 days)
        $pendingApprovals = OutPass::where('tenant_id', $tenantId)
            ->where('status', OutPassStatus::PENDING)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        
        // Approvals last 7 days
        $approvalsLast7d = OutPass::where('tenant_id', $tenantId)
            ->where('status', OutPassStatus::APPROVED)
            ->where('decided_at', '>=', now()->subDays(7))
            ->count();
        
        // Late returns
        $lateReturns = OutPass::where('tenant_id', $tenantId)
            ->where('status', OutPassStatus::APPROVED)
            ->where('expected_in_date', '<', now())
            ->whereNull('actual_in_date')
            ->count();
        
        // Recent incidents (last 7 days)
        $incidentsLast7d = \App\Models\Incident::where('tenant_id', $tenantId)
            ->where('opened_at', '>=', now()->subDays(7))
            ->count();
        
        return response()->json([
            'data' => [
                'pending_approvals' => $pendingApprovals,
                'approvals_last_7d' => $approvalsLast7d,
                'late_returns' => $lateReturns,
                'incidents_last_7d' => $incidentsLast7d,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function approvals(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Verify Rector role
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access approvals.',
            ], Response::HTTP_FORBIDDEN);
        }
        
        $tenantId = $user->tenant_id;
        
        if (!$tenantId) {
            \Log::error('RectorDashboardController::approvals: No tenant_id for user', ['user_id' => $user->id]);
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }
        
        // Build query for pending out-passes
        $query = OutPass::with(['student.user', 'hostel'])
            ->where('tenant_id', $tenantId)
            ->where('status', OutPassStatus::PENDING)
            ->orderBy('created_at', 'desc');
        
        // Filters
        if ($request->has('hostel_id')) {
            $query->where('hostel_id', $request->hostel_id);
        }
        
        if ($request->has('emergency')) {
            // Note: OutPass doesn't have is_emergency, check if overnight or reason matches
            // For now, filter by overnight status
            if ($request->boolean('emergency')) {
                $query->where('overnight', true);
            }
        }
        
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->date('from_date'));
        }
        
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->date('to_date'));
        }
        
        $perPage = min($request->get('per_page', 20), 100); // Max 100 per page
        $outPasses = $query->paginate($perPage);
        
        return response()->json([
            'data' => $outPasses->items(),
            'meta' => [
                'current_page' => $outPasses->currentPage(),
                'total' => $outPasses->total(),
                'per_page' => $outPasses->perPage(),
                'last_page' => $outPasses->lastPage(),
            ],
        ]);
    }

    /**
     * Bulk approve multiple out-passes.
     *
     * Uses database transactions to ensure atomicity.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Verify user is Rector
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can perform bulk approvals.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'outpass_ids' => [
                'required',
                'array',
                'min:1',
                'max:50', // Limit batch size
            ],
            'outpass_ids.*' => [
                'required',
                'integer',
                'exists:out_passes,id',
            ],
            'note' => [
                'sometimes',
                'string',
                'max:500',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Invalid out-pass IDs provided.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $outpassIds = $request->input('outpass_ids');
        $note = $request->input('note', 'Bulk approved by Rector');

        $results = [
            'approved' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Process in transaction
        DB::beginTransaction();

        try {
            // Load all out-passes (we're in tenant context, no tenant_id needed)
            $outPasses = OutPass::whereIn('id', $outpassIds)
                ->where('status', OutPassStatus::PENDING)
                ->with('student.user')
                ->lockForUpdate() // Lock rows for update to prevent race conditions
                ->get();

            $validIds = $outPasses->pluck('id')->toArray();
            $invalidIds = array_diff($outpassIds, $validIds);

            // Track invalid IDs
            foreach ($invalidIds as $invalidId) {
                $results['failed']++;
                $results['errors'][] = "Out-pass #{$invalidId} not found or not pending";
            }

            foreach ($outPasses as $outPass) {
                try {
                    // Check if expired (24-hour rule)
                    if ($outPass->status !== OutPassStatus::PENDING) {
                        $results['failed']++;
                        $results['errors'][] = "Out-pass #{$outPass->id} is not pending (current status: {$outPass->status->value})";
                        continue;
                    }

                    $expiryTime = $outPass->requested_at->copy()->addHours(24);
                    if (now()->isAfter($expiryTime)) {
                        // Auto-expire
                        $previousStatus = $outPass->status;
                        $outPass->forceFill([
                            'status' => OutPassStatus::EXPIRED,
                            'decided_at' => now(),
                            'note' => 'Automatically expired after 24 hours',
                            'decision_by' => null,
                        ])->save();

                        $outPass->recordHistory(
                            $previousStatus,
                            OutPassStatus::EXPIRED,
                            'Automatically expired',
                            label: 'Expired',
                            description: 'Out-pass expired after 24 hours'
                        );

                        $results['failed']++;
                        $results['errors'][] = "Out-pass #{$outPass->id} has expired";
                        continue;
                    }

                    // Approve the out-pass
                    $previousStatus = $outPass->status;
                    $outPass->forceFill([
                        'status' => OutPassStatus::APPROVED,
                        'decided_at' => now(),
                        'note' => $note,
                        'decision_by' => $user->id,
                    ])->save();

                    $outPass->recordHistory(
                        $previousStatus,
                        OutPassStatus::APPROVED,
                        $note,
                        actorId: $user->id,
                        label: 'Bulk Approved',
                        description: "Bulk approved by {$user->name}"
                    );

                    $studentUserId = $outPass->student?->user?->id;
                    if ($studentUserId) {
                        dispatch(new \App\Jobs\SendApprovalNotification(
                            approvalType: 'outpass',
                            recordId: (int) $outPass->id,
                            decision: 'approved',
                            note: $note,
                            studentId: (int) $studentUserId,
                            rectorId: (int) $user->id,
                            tenantId: (string) $user->tenant_id
                        ));
                    }

                    $results['approved']++;
                } catch (\Exception $e) {
                    Log::error('Failed to approve out-pass in bulk operation', [
                        'outpass_id' => $outPass->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);

                    $results['failed']++;
                    $results['errors'][] = "Out-pass #{$outPass->id}: {$e->getMessage()}";
                }
            }

            DB::commit();

            Log::info('Bulk approval completed', [
                'user_id' => $user->id,
                'total_requested' => count($outpassIds),
                'approved' => $results['approved'],
                'failed' => $results['failed'],
            ]);

            // Return success even if some failed (partial success)
            return response()->json([
                'message' => $results['failed'] === 0 
                    ? "Successfully approved {$results['approved']} out-pass(es)"
                    : "Approved {$results['approved']} out-pass(es), {$results['failed']} failed",
                'data' => $results,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Bulk approval transaction failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Bulk Approval Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'An error occurred while processing bulk approval. No out-passes were approved.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function incidents(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Verify authentication
        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthorized',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Verify Rector role
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access incidents.',
            ], Response::HTTP_FORBIDDEN);
        }

        $days = min($request->get('days', 7), 30); // Max 30 days
        $hostelId = $request->get('hostel_id');

        try {
            $query = \App\Models\Incident::with(['hostel'])
                ->where('opened_at', '>=', now()->subDays($days))
                ->orderBy('opened_at', 'desc');

            if ($hostelId) {
                $query->where('hostel_id', $hostelId);
            }

            $perPage = min($request->get('per_page', 20), 100);
            $incidents = $query->paginate($perPage);

            return response()->json([
                'data' => $incidents->items(),
                'meta' => [
                    'current_page' => $incidents->currentPage(),
                    'total' => $incidents->total(),
                    'per_page' => $incidents->perPage(),
                    'last_page' => $incidents->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Rector incidents endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'An error occurred while fetching incidents.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function hostelsHealth(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Verify authentication
        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthorized',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Verify Rector role
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access hostel health.',
            ], Response::HTTP_FORBIDDEN);
        }

        $hostels = \App\Models\Hostel::with('campus')->get();
        $healthData = [];

        foreach ($hostels as $hostel) {
            // Attendance compliance (last 7 days)
            $attendanceCompliance = 0;
            try {
                $attendanceSessions = \App\Domain\Attendance\Models\AttendanceSession::where('hostel_id', $hostel->id)
                    ->where('scheduled_at', '>=', now()->subDays(7))
                    ->get();
                
                $totalSessions = $attendanceSessions->count();
                $closedSessions = $attendanceSessions->where('status', 'closed')->count();
                $attendanceCompliance = $totalSessions > 0 
                    ? round(($closedSessions / $totalSessions) * 100, 1) 
                    : 0;
            } catch (\Exception $e) {
                Log::debug('Attendance module not available for hostel health', ['error' => $e->getMessage()]);
            }

            // Open incidents
            $openIncidents = \App\Models\Incident::where('hostel_id', $hostel->id)
                ->where('status', 'Open')
                ->count();

            // Open tickets
            $openTickets = \App\Models\Ticket::where('hostel_id', $hostel->id)
                ->whereIn('status', ['Open', 'InProgress'])
                ->count();

            // Checklist compliance (today)
            $checklistsToday = 0;
            $checklistsCompletedToday = 0;
            $checklistCompliance = 0;
            try {
                if (class_exists(\App\Domain\Checklists\Models\ChecklistInstance::class)) {
                    $checklistsToday = \App\Domain\Checklists\Models\ChecklistInstance::where('hostel_id', $hostel->id)
                        ->whereDate('created_at', today())
                        ->count();
                    $checklistsCompletedToday = \App\Domain\Checklists\Models\ChecklistInstance::where('hostel_id', $hostel->id)
                        ->whereDate('created_at', today())
                        ->where('status', 'completed')
                        ->count();
                    $checklistCompliance = $checklistsToday > 0 
                        ? round(($checklistsCompletedToday / $checklistsToday) * 100, 1) 
                        : 0;
                }
            } catch (\Exception $e) {
                // Checklists module may not be available
                Log::debug('Checklists module not available', ['error' => $e->getMessage()]);
            }

            // Late returns (today)
            $lateReturns = OutPass::where('hostel_id', $hostel->id)
                ->where('status', OutPassStatus::APPROVED)
                ->where('expected_in_date', '<', now())
                ->whereNull('actual_in_date')
                ->count();

            // Overall health score (0-100)
            $healthScore = 100;
            if ($attendanceCompliance < 80) $healthScore -= 20;
            if ($openIncidents > 5) $healthScore -= 15;
            if ($openTickets > 10) $healthScore -= 15;
            if ($checklistCompliance < 80) $healthScore -= 10;
            if ($lateReturns > 5) $healthScore -= 10;
            $healthScore = max(0, $healthScore);

            $healthData[] = [
                'hostel_id' => $hostel->id,
                'hostel_name' => $hostel->name,
                'campus_name' => $hostel->campus->name ?? null,
                'health_score' => $healthScore,
                'attendance_compliance' => $attendanceCompliance,
                'open_incidents' => $openIncidents,
                'open_tickets' => $openTickets,
                'checklist_compliance' => $checklistCompliance,
                'late_returns' => $lateReturns,
                'status' => $healthScore >= 80 ? 'healthy' : ($healthScore >= 60 ? 'warning' : 'critical'),
            ];
        }

        // Sort by health score (lowest first)
        usort($healthData, fn($a, $b) => $a['health_score'] <=> $b['health_score']);

        return response()->json([
            'data' => $healthData,
            'summary' => [
                'total_hostels' => count($healthData),
                'healthy' => count(array_filter($healthData, fn($h) => $h['status'] === 'healthy')),
                'warning' => count(array_filter($healthData, fn($h) => $h['status'] === 'warning')),
                'critical' => count(array_filter($healthData, fn($h) => $h['status'] === 'critical')),
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Verify authentication
        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthorized',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Verify Rector role
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access analytics.',
            ], Response::HTTP_FORBIDDEN);
        }

        $days = min($request->get('days', 7), 30); // Max 30 days
        $startDate = now()->subDays($days);

        // Out-pass analytics
        $outPassStats = OutPass::where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as approved,
                COUNT(CASE WHEN status = ? THEN 1 END) as declined,
                COUNT(CASE WHEN status = ? THEN 1 END) as expired,
                AVG(EXTRACT(EPOCH FROM (decided_at - created_at))/3600) as avg_approval_time_hours
            ', [OutPassStatus::APPROVED->value, OutPassStatus::DECLINED->value, OutPassStatus::EXPIRED->value])
            ->first();

        // Attendance analytics
        $attendanceStats = null;
        try {
            $attendanceStats = \App\Domain\Attendance\Models\AttendanceSession::where('scheduled_at', '>=', $startDate)
                ->selectRaw('
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN status = ? THEN 1 END) as closed_sessions,
                    AVG(EXTRACT(EPOCH FROM (closed_at - scheduled_at))/3600) as avg_closure_time_hours
                ', ['closed'])
                ->first();
        } catch (\Exception $e) {
            Log::debug('Attendance module not available', ['error' => $e->getMessage()]);
        }

        // Ticket analytics
        $ticketStats = \App\Models\Ticket::where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as open,
                COUNT(CASE WHEN status = ? THEN 1 END) as resolved,
                AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/86400) as avg_resolution_days
            ', ['Open', 'Resolved'])
            ->first();

        // Incident analytics
        $incidentStats = \App\Models\Incident::where('opened_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as open,
                COUNT(CASE WHEN status = ? THEN 1 END) as closed
            ', ['Open', 'Closed'])
            ->first();

        return response()->json([
            'data' => [
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => now()->toIso8601String(),
                    'days' => $days,
                ],
                'outpasses' => [
                    'total' => (int) ($outPassStats->total ?? 0),
                    'approved' => (int) ($outPassStats->approved ?? 0),
                    'declined' => (int) ($outPassStats->declined ?? 0),
                    'expired' => (int) ($outPassStats->expired ?? 0),
                    'avg_approval_time_hours' => round((float) ($outPassStats->avg_approval_time_hours ?? 0), 2),
                ],
                'attendance' => [
                    'total_sessions' => (int) ($attendanceStats->total_sessions ?? 0),
                    'closed_sessions' => (int) ($attendanceStats->closed_sessions ?? 0),
                    'compliance_percent' => ($attendanceStats && $attendanceStats->total_sessions > 0) 
                        ? round(($attendanceStats->closed_sessions / $attendanceStats->total_sessions) * 100, 1) 
                        : 0,
                    'avg_closure_time_hours' => round((float) ($attendanceStats->avg_closure_time_hours ?? 0), 2),
                ],
                'tickets' => [
                    'total' => (int) ($ticketStats->total ?? 0),
                    'open' => (int) ($ticketStats->open ?? 0),
                    'resolved' => (int) ($ticketStats->resolved ?? 0),
                    'avg_resolution_days' => round((float) ($ticketStats->avg_resolution_days ?? 0), 2),
                ],
                'incidents' => [
                    'total' => (int) ($incidentStats->total ?? 0),
                    'open' => (int) ($incidentStats->open ?? 0),
                    'closed' => (int) ($incidentStats->closed ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Get leaves (combined Leave + Sick Leave) with optional status filter
     */
    public function leaves(Request $request): JsonResponse
    {
        \Log::info('RectorDashboardController::leaves - ENTRY', [
            'request_url' => $request->fullUrl(),
            'request_method' => $request->method(),
            'user_id' => Auth::id(),
        ]);
        
        $user = Auth::user();
        
        \Log::info('RectorDashboardController::leaves - User check', [
            'user_id' => $user->id,
            'user_roles' => $user->roles->pluck('name')->toArray(),
            'has_rector_role' => $user->hasRole('Rector'),
        ]);
        
        if (!$user->hasRole('Rector')) {
            \Log::warning('RectorDashboardController::leaves - User is not Rector', [
                'user_id' => $user->id,
                'roles' => $user->roles->pluck('name')->toArray(),
            ]);
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access leave approvals.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $user->tenant_id;
        
        \Log::info('RectorDashboardController::leaves - Tenant check', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
        ]);
        
        if (!$tenantId) {
            \Log::error('RectorDashboardController::leaves: No tenant_id for user', [
                'user_id' => $user->id,
            ]);
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }
        
        $perPage = $request->get('per_page', 20);
        $status = $request->get('status'); // Can be 'all', 'pending', 'approved', 'rejected', or null (null means all)
        
        // Debug: Check total leaves count without filter
        $totalLeavesCount = \App\Domain\Leaves\Models\Leave::where('tenant_id', $tenantId)->count();
        $totalSickLeavesCount = \App\Domain\SickLeaves\Models\SickLeave::where('tenant_id', $tenantId)->count();
        
        \Log::info('RectorDashboardController::leaves', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'status_filter' => $status,
            'total_leaves_in_db' => $totalLeavesCount,
            'total_sick_leaves_in_db' => $totalSickLeavesCount,
        ]);

        // Combine Leave and SickLeave queries
        try {
            $leavesQuery = \App\Domain\Leaves\Models\Leave::with(['student.user', 'hostel'])
                ->where('tenant_id', $tenantId);
            
            // Apply status filter if provided and not 'all'
            if ($status && $status !== 'all') {
                $leavesQuery->where('status', $status);
            }
            
            $leaves = $leavesQuery->select([
                    'id',
                    'unique_id',
                    'student_id',
                    'hostel_id',
                    'title',
                    'description',
                    'reason_for_leave',
                    'from_date',
                    'to_date',
                    'status',
                    'submitted_at',
                    'sla_due_at',
                    'sla_breached_at',
                    'created_at',
                ])
                ->get()
                ->map(function ($leave) {
                    return [
                        'id' => (string) $leave->id,
                        'type' => 'leave',
                        'unique_id' => $leave->unique_id ?? "LEV-{$leave->id}",
                        'student_name' => $leave->student?->user?->name ?? 'Unknown',
                        'hostel_name' => $leave->hostel?->name ?? 'Unknown',
                        'title' => $leave->title,
                        'description' => $leave->description,
                        'reason_for_leave' => $leave->reason_for_leave, // Changed from 'reason' to match mobile app
                        'from_date' => $leave->from_date?->format('Y-m-d'),
                        'to_date' => $leave->to_date?->format('Y-m-d'),
                        'status' => $leave->status,
                        'submitted_at' => $leave->submitted_at?->format('Y-m-d H:i:s') ?? $leave->created_at->format('Y-m-d H:i:s'),
                        'submitted_date' => $leave->submitted_at?->format('Y-m-d H:i:s') ?? $leave->created_at->format('Y-m-d H:i:s'), // Added for mobile app compatibility
                        'created_at' => $leave->created_at->toIso8601String(), // Added for mobile app compatibility
                        'sla_due_at' => $leave->sla_due_at?->format('Y-m-d H:i:s'),
                        'sla_breached_at' => $leave->sla_breached_at?->format('Y-m-d H:i:s'),
                        'sla_status' => $this->calculateSLAStatus($leave->submitted_at ?? $leave->created_at, $leave->sla_breached_at),
                    ];
                });
        } catch (\Exception $e) {
            \Log::error('RectorDashboardController::leaves - Error fetching leaves', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId,
            ]);
            $leaves = collect([]);
        }

        try {
            $sickLeavesQuery = \App\Domain\SickLeaves\Models\SickLeave::with(['student.user', 'hostel'])
                ->where('tenant_id', $tenantId);
            
            // Apply status filter if provided and not 'all'
            if ($status && $status !== 'all') {
                $sickLeavesQuery->where('status', $status);
            }
            
            $sickLeaves = $sickLeavesQuery->select([
                    'id',
                    'unique_id',
                    'student_id',
                    'hostel_id',
                    'title',
                    'description',
                    'illness',
                    'status',
                    'submitted_at',
                    'sla_due_at',
                    'sla_breached_at',
                    'created_at',
                ])
                ->get()
                ->map(function ($sickLeave) {
                    return [
                        'id' => (string) $sickLeave->id,
                        'type' => 'sick_leave',
                        'unique_id' => $sickLeave->unique_id ?? "SL-{$sickLeave->id}",
                        'student_name' => $sickLeave->student?->user?->name ?? 'Unknown',
                        'hostel_name' => $sickLeave->hostel?->name ?? 'Unknown',
                        'title' => $sickLeave->title,
                        'description' => $sickLeave->description,
                        'reason_for_leave' => $sickLeave->illness, // Map illness to reason_for_leave for mobile app compatibility
                        'from_date' => null,
                        'to_date' => null,
                        'status' => $sickLeave->status,
                        'submitted_at' => $sickLeave->submitted_at?->format('Y-m-d H:i:s') ?? $sickLeave->created_at->format('Y-m-d H:i:s'),
                        'submitted_date' => $sickLeave->submitted_at?->format('Y-m-d H:i:s') ?? $sickLeave->created_at->format('Y-m-d H:i:s'), // Added for mobile app compatibility
                        'created_at' => $sickLeave->created_at->toIso8601String(), // Added for mobile app compatibility
                        'sla_due_at' => $sickLeave->sla_due_at?->format('Y-m-d H:i:s'),
                        'sla_breached_at' => $sickLeave->sla_breached_at?->format('Y-m-d H:i:s'),
                        'sla_status' => $this->calculateSLAStatus($sickLeave->submitted_at ?? $sickLeave->created_at, $sickLeave->sla_breached_at),
                    ];
                });
        } catch (\Exception $e) {
            \Log::error('RectorDashboardController::leaves - Error fetching sick leaves', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId,
            ]);
            $sickLeaves = collect([]);
        }

        $combined = $leaves->concat($sickLeaves)->sortBy(function ($item) {
            return $item['submitted_at'] ?? $item['created_at'] ?? '1970-01-01';
        }, SORT_REGULAR, true)->values(); // Sort descending (newest first)

        \Log::info('RectorDashboardController::leaves result', [
            'leaves_count' => $leaves->count(),
            'sick_leaves_count' => $sickLeaves->count(),
            'total_count' => $combined->count(),
            'tenant_id' => $tenantId,
            'status_filter' => $status,
            'combined_data_sample' => $combined->take(1)->toArray(),
        ]);

        return response()->json([
            'data' => $combined->values()->all(), // Ensure it's a proper array, not a collection
            'meta' => [
                'total' => $combined->count(),
            ],
        ]);
    }

    /**
     * Show specific leave details
     */
    public function showLeave(Request $request, string $leave): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthorized',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Verify Rector role
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access leave details.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $user->tenant_id;
        
        if (!$tenantId) {
            \Log::error('RectorDashboardController::showLeave: No tenant_id for user', [
                'user_id' => $user->id,
            ]);
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Unable to determine tenant context.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            // Try to find as Leave first
            $leaveRecord = \App\Domain\Leaves\Models\Leave::with(['student.user', 'hostel'])
                ->where('tenant_id', $tenantId)
                ->where('id', $leave)
                ->first();
            
            $isSickLeave = false;
            
            // If not found, try SickLeave
            if (!$leaveRecord) {
                $leaveRecord = \App\Domain\SickLeaves\Models\SickLeave::with(['student.user', 'hostel'])
                    ->where('tenant_id', $tenantId)
                    ->where('id', $leave)
                    ->first();
                $isSickLeave = true;
            }
            
            if (!$leaveRecord) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => 'Leave request not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            // Format the response based on leave type
            if ($isSickLeave) {
                $data = [
                    'id' => (string) $leaveRecord->id,
                    'type' => 'sick_leave',
                    'unique_id' => $leaveRecord->unique_id ?? "SL-{$leaveRecord->id}",
                    'student_name' => $leaveRecord->student?->user?->name ?? 'Unknown',
                    'hostel_name' => $leaveRecord->hostel?->name ?? 'Unknown',
                    'title' => $leaveRecord->title,
                    'description' => $leaveRecord->description,
                    'reason_for_leave' => $leaveRecord->illness,
                    'illness' => $leaveRecord->illness,
                    'illness_details' => $leaveRecord->illness_details ?? null,
                    'need_medical_attention' => $leaveRecord->need_medical_attention ?? false,
                    'contact_parents' => $leaveRecord->contact_parents ?? false,
                    'from_date' => null,
                    'to_date' => null,
                    'status' => $leaveRecord->status,
                    'rejection_reason' => $leaveRecord->rejection_reason ?? null,
                    'submitted_at' => $leaveRecord->submitted_at?->format('Y-m-d H:i:s') ?? $leaveRecord->created_at->format('Y-m-d H:i:s'),
                    'submitted_date' => $leaveRecord->submitted_at?->format('Y-m-d H:i:s') ?? $leaveRecord->created_at->format('Y-m-d H:i:s'),
                    'created_at' => $leaveRecord->created_at->toIso8601String(),
                    'sla_due_at' => $leaveRecord->sla_due_at?->format('Y-m-d H:i:s'),
                    'sla_breached_at' => $leaveRecord->sla_breached_at?->format('Y-m-d H:i:s'),
                    'sla_status' => $this->calculateSLAStatus($leaveRecord->submitted_at ?? $leaveRecord->created_at, $leaveRecord->sla_breached_at),
                    'approved_by' => $leaveRecord->approved_by ?? null,
                    'approved_at' => $leaveRecord->approved_at?->toIso8601String(),
                ];
            } else {
                $data = [
                    'id' => (string) $leaveRecord->id,
                    'type' => 'leave',
                    'unique_id' => $leaveRecord->unique_id ?? "LEV-{$leaveRecord->id}",
                    'student_name' => $leaveRecord->student?->user?->name ?? 'Unknown',
                    'hostel_name' => $leaveRecord->hostel?->name ?? 'Unknown',
                    'title' => $leaveRecord->title,
                    'description' => $leaveRecord->description,
                    'reason_for_leave' => $leaveRecord->reason_for_leave,
                    'from_date' => $leaveRecord->from_date?->format('Y-m-d'),
                    'to_date' => $leaveRecord->to_date?->format('Y-m-d'),
                    'emergency_contact' => $leaveRecord->emergency_contact ?? null,
                    'status' => $leaveRecord->status,
                    'rejection_reason' => $leaveRecord->rejection_reason ?? null,
                    'submitted_at' => $leaveRecord->submitted_at?->format('Y-m-d H:i:s') ?? $leaveRecord->created_at->format('Y-m-d H:i:s'),
                    'submitted_date' => $leaveRecord->submitted_at?->format('Y-m-d H:i:s') ?? $leaveRecord->created_at->format('Y-m-d H:i:s'),
                    'created_at' => $leaveRecord->created_at->toIso8601String(),
                    'sla_due_at' => $leaveRecord->sla_due_at?->format('Y-m-d H:i:s'),
                    'sla_breached_at' => $leaveRecord->sla_breached_at?->format('Y-m-d H:i:s'),
                    'sla_status' => $this->calculateSLAStatus($leaveRecord->submitted_at ?? $leaveRecord->created_at, $leaveRecord->sla_breached_at),
                    'approved_by' => $leaveRecord->approved_by ?? null,
                    'approved_at' => $leaveRecord->approved_at?->toIso8601String(),
                ];
            }

            return response()->json([
                'data' => $data,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('RectorDashboardController::showLeave - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'leave_id' => $leave,
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to fetch leave details: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Approve a leave request
     */
    public function approveLeave(Request $request, string $leaveId): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can approve leaves.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $user->tenant_id;
        
        if (!$tenantId) {
            \Log::error('RectorDashboardController::approveLeave: No tenant_id for user', [
                'user_id' => $user->id,
            ]);
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Unable to determine tenant context.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            // Try to find as Leave first
            $leave = \App\Domain\Leaves\Models\Leave::where('tenant_id', $tenantId)
                ->where('id', $leaveId)
                ->first();
            
            $isSickLeave = false;
            
            // If not found, try SickLeave
            if (!$leave) {
                $leave = \App\Domain\SickLeaves\Models\SickLeave::where('tenant_id', $tenantId)
                    ->where('id', $leaveId)
                    ->first();
                $isSickLeave = true;
            }
            
            if (!$leave) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => 'Leave request not found.',
                ], Response::HTTP_NOT_FOUND);
            }
            
            if ($leave->status !== 'pending') {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/invalid_state',
                    'title' => 'Invalid State',
                    'status' => Response::HTTP_BAD_REQUEST,
                    'detail' => 'Leave request is not in pending status.',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Update the leave
            $leave->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            
            // Get student user ID for notification
            $studentUserId = $leave->student?->user?->id;
            
            if ($studentUserId) {
                // Send notification
                dispatch(new \App\Jobs\SendApprovalNotification(
                    approvalType: $isSickLeave ? 'sick_leave' : 'leave',
                    recordId: $leave->id,
                    decision: 'approved',
                    note: $request->input('note'),
                    studentId: $studentUserId,
                    rectorId: $user->id,
                    tenantId: $tenantId
                ));
            }
            
            \Log::info('RectorDashboardController::approveLeave - Success', [
                'user_id' => $user->id,
                'leave_id' => $leaveId,
                'leave_type' => $isSickLeave ? 'sick_leave' : 'leave',
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'message' => ($isSickLeave ? 'Sick leave' : 'Leave') . ' request approved successfully.',
                'data' => [
                    'id' => (string) $leave->id,
                    'status' => $leave->status,
                    'approved_at' => $leave->approved_at?->toIso8601String(),
                ],
            ]);
            
        } catch (\Exception $e) {
            \Log::error('RectorDashboardController::approveLeave - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'leave_id' => $leaveId,
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to approve leave request: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reject a leave request
     */
    public function rejectLeave(Request $request, string $leaveId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = Auth::user();
        if (!$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can reject leaves.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $user->tenant_id;
        
        if (!$tenantId) {
            \Log::error('RectorDashboardController::rejectLeave: No tenant_id for user', [
                'user_id' => $user->id,
            ]);
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Unable to determine tenant context.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            // Try to find as Leave first
            $leave = \App\Domain\Leaves\Models\Leave::where('tenant_id', $tenantId)
                ->where('id', $leaveId)
                ->first();
            
            $isSickLeave = false;
            
            // If not found, try SickLeave
            if (!$leave) {
                $leave = \App\Domain\SickLeaves\Models\SickLeave::where('tenant_id', $tenantId)
                    ->where('id', $leaveId)
                    ->first();
                $isSickLeave = true;
            }
            
            if (!$leave) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => 'Leave request not found.',
                ], Response::HTTP_NOT_FOUND);
            }
            
            if ($leave->status !== 'pending') {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/invalid_state',
                    'title' => 'Invalid State',
                    'status' => Response::HTTP_BAD_REQUEST,
                    'detail' => 'Leave request is not in pending status.',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Update the leave (using approved_by/approved_at for consistency with Filament)
            $leave->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('reason'),
                'approved_by' => $user->id, // Filament uses approved_by even for rejections
                'approved_at' => now(),
            ]);
            
            // Get student user ID for notification
            $studentUserId = $leave->student?->user?->id;
            
            if ($studentUserId) {
                // Send notification
                dispatch(new \App\Jobs\SendApprovalNotification(
                    approvalType: $isSickLeave ? 'sick_leave' : 'leave',
                    recordId: $leave->id,
                    decision: 'rejected',
                    note: $request->input('reason'),
                    studentId: $studentUserId,
                    rectorId: $user->id,
                    tenantId: $tenantId
                ));
            }
            
            \Log::info('RectorDashboardController::rejectLeave - Success', [
                'user_id' => $user->id,
                'leave_id' => $leaveId,
                'leave_type' => $isSickLeave ? 'sick_leave' : 'leave',
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'message' => ($isSickLeave ? 'Sick leave' : 'Leave') . ' request rejected successfully.',
                'data' => [
                    'id' => (string) $leave->id,
                    'status' => $leave->status,
                    'rejected_at' => $leave->rejected_at?->toIso8601String(),
                ],
            ]);
            
        } catch (\Exception $e) {
            \Log::error('RectorDashboardController::rejectLeave - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'leave_id' => $leaveId,
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to reject leave request: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk approve leaves
     */
    public function bulkApproveLeaves(Request $request): JsonResponse
    {
        $request->validate([
            'leave_ids' => 'required|array',
            'leave_ids.*' => 'string',
            'note' => 'nullable|string|max:500',
        ]);

        // Implementation for bulk approval
        return response()->json([
            'message' => 'Bulk leave approval endpoint - implementation needed',
        ]);
    }

    /**
     * Calculate SLA status for display
     */
    private function calculateSLAStatus($submittedAt, $breachedAt): array
    {
        if ($breachedAt) {
            $hoursBreached = now()->diffInHours($breachedAt);
            return [
                'status' => 'breached',
                'message' => "Overdue: +{$hoursBreached}h",
                'color' => 'danger',
            ];
        }

        $hoursElapsed = now()->diffInHours($submittedAt);
        $slaHours = 4; // 4 hours for leaves

        if ($hoursElapsed >= $slaHours) {
            return [
                'status' => 'breached',
                'message' => 'Overdue',
                'color' => 'danger',
            ];
        } elseif ($hoursElapsed >= $slaHours * 0.75) { // 75% threshold
            $remaining = $slaHours - $hoursElapsed;
            return [
                'status' => 'warning',
                'message' => "Due: {$remaining}h",
                'color' => 'warning',
            ];
        } else {
            $remaining = $slaHours - $hoursElapsed;
            return [
                'status' => 'ok',
                'message' => "{$remaining}h left",
                'color' => 'success',
            ];
        }
    }
}
