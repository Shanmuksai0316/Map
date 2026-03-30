<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\RectorReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RectorReportController extends Controller
{
    public function __construct(private readonly RectorReportService $reportService) {}

    /**
     * Generate monthly approval report
     */
    public function generateMonthly(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'format' => 'required|in:pdf,csv',
        ]);

        $user = Auth::user();
        if (!$user || !$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $tenantId = $user->tenant_id;

            if ($request->format === 'pdf') {
                $url = $this->reportService->generateMonthlyPDF($tenantId, $request->month, $request->year);
            } else {
                $url = $this->reportService->generateMonthlyCSV($tenantId, $request->month, $request->year);
            }

            return response()->json([
                'data' => [
                    'download_url' => $url,
                    'format' => $request->format,
                    'month' => $request->month,
                    'year' => $request->year,
                    'generated_at' => now()->toISOString(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/report_generation_failed',
                'title' => 'Report Generation Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to generate the requested report. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get approval history (for mobile access)
     */
    public function getApprovalHistory(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|in:outpass,leave,sick_leave',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $user = Auth::user();
        if (!$user || !$user->hasRole('Rector')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rectors can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $tenantId = $user->tenant_id;
            $perPage = $request->per_page ?? 20;

            // Build three Query Builder queries

            // 1. Out-Pass approvals query
            $outPassQuery = \DB::table('out_passes')
                ->select([
                    'out_passes.id',
                    \DB::raw("'Out-Pass' as type"),
                    'out_passes.unique_id',
                    'students.name as student_name',
                    \DB::raw('NULL as hostel_name'),
                    'out_passes.status as decision',
                    'out_passes.decided_at',
                    'actors.name as decided_by',
                    'out_passes.note',
                ])
                ->join('users as students', 'out_passes.student_id', '=', 'students.id')
                ->leftJoin('users as actors', 'out_passes.decision_by', '=', 'actors.id')
                ->where('out_passes.tenant_id', $tenantId)
                ->whereIn('out_passes.status', ['approved', 'declined']);

            // Apply date filters to out-pass query
            if ($request->from_date) {
                $outPassQuery->whereDate('out_passes.decided_at', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $outPassQuery->whereDate('out_passes.decided_at', '<=', $request->to_date);
            }

            // 2. Leave approvals query
            $leaveQuery = \DB::table('leaves')
                ->select([
                    'leaves.id',
                    \DB::raw("'Leave' as type"),
                    'leaves.unique_id',
                    'students.name as student_name',
                    \DB::raw('NULL as hostel_name'),
                    'leaves.status as decision',
                    'leaves.approved_at as decided_at',
                    'actors.name as decided_by',
                    'leaves.rejection_reason as note',
                ])
                ->join('users as students', 'leaves.student_id', '=', 'students.id')
                ->leftJoin('users as actors', 'leaves.approved_by', '=', 'actors.id')
                ->where('leaves.tenant_id', $tenantId)
                ->whereIn('leaves.status', ['approved', 'rejected']);

            // Apply date filters to leave query
            if ($request->from_date) {
                $leaveQuery->whereDate('leaves.approved_at', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $leaveQuery->whereDate('leaves.approved_at', '<=', $request->to_date);
            }

            // 3. Sick Leave approvals query
            $sickLeaveQuery = \DB::table('sick_leaves')
                ->select([
                    'sick_leaves.id',
                    \DB::raw("'Sick Leave' as type"),
                    'sick_leaves.unique_id',
                    'students.name as student_name',
                    \DB::raw('NULL as hostel_name'),
                    'sick_leaves.status as decision',
                    'sick_leaves.approved_at as decided_at',
                    'actors.name as decided_by',
                    'sick_leaves.rejection_reason as note',
                ])
                ->join('users as students', 'sick_leaves.student_id', '=', 'students.id')
                ->leftJoin('users as actors', 'sick_leaves.approved_by', '=', 'actors.id')
                ->where('sick_leaves.tenant_id', $tenantId)
                ->whereIn('sick_leaves.status', ['approved', 'rejected']);

            // Apply date filters to sick leave query
            if ($request->from_date) {
                $sickLeaveQuery->whereDate('sick_leaves.approved_at', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $sickLeaveQuery->whereDate('sick_leaves.approved_at', '<=', $request->to_date);
            }

            // 4. Combine using unionAll()
            $unionQuery = $outPassQuery->unionAll($leaveQuery)->unionAll($sickLeaveQuery);

            // 5. Wrap UNION query using DB::query()->fromSub()
            $query = \DB::query()->fromSub($unionQuery, 'approval_history');

            // 6. Apply optional type filter
            if ($request->type) {
                $typeMap = [
                    'outpass' => 'Out-Pass',
                    'leave' => 'Leave',
                    'sick_leave' => 'Sick Leave',
                ];
                $query->where('type', $typeMap[$request->type]);
            }

            // 7. Apply orderBy and paginate
            $results = $query
                ->orderByDesc('decided_at')
                ->paginate($perPage, ['*'], 'page', $request->page ?? 1);

            return response()->json([
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            // Add proper error logging
            \Log::error('Approval history query failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'request_params' => $request->all(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/query_failed',
                'title' => 'Query Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve approval history.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
