<?php

namespace App\Http\Controllers\Api\V1\CampusManager;

use App\Http\Controllers\Controller;
use App\Domain\Checklists\Models\ChecklistInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Checklist Export Controller for Campus Manager
 *
 * Provides export functionality for checklist compliance reports
 */
class ChecklistExportController extends Controller
{
    /**
     * Export checklist compliance report as CSV
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function exportCsv(Request $request): StreamedResponse
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
            ->leftJoin('users as manager', 'ci.manager_user_id', '=', 'manager.id')
            ->where('ci.tenant_id', $tenantId)
            ->whereBetween('ci.date', [$fromDate, $toDate]);

        if (!empty($validated['role'])) {
            $query->where('ci.role', $validated['role']);
        }

        if (!empty($validated['staff_id'])) {
            $query->where('ci.assignee_user_id', $validated['staff_id']);
        }

        $data = $query->select(
            'ci.date',
            'u.name as staff_name',
            'u.email as staff_email',
            'ci.role',
            'ct.title as checklist_title',
            'ci.status',
            'ci.review_status',
            'ci.total_tasks',
            'ci.completed_tasks',
            'ci.submitted_at',
            'ci.reviewed_at',
            'manager.name as reviewed_by',
            'ci.manager_note'
        )
            ->orderBy('ci.date', 'desc')
            ->orderBy('u.name')
            ->get();

        $filename = sprintf('checklist_report_%s_to_%s.csv', $fromDate, $toDate);

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // CSV Header
            fputcsv($handle, [
                'Date',
                'Staff Name',
                'Staff Email',
                'Role',
                'Checklist Title',
                'Status',
                'Review Status',
                'Total Tasks',
                'Completed Tasks',
                'Completion %',
                'Submitted At',
                'Reviewed At',
                'Reviewed By',
                'Manager Note',
                'Is Overdue',
            ]);

            // CSV Data
            foreach ($data as $row) {
                $completionPct = $row->total_tasks > 0
                    ? round(($row->completed_tasks / $row->total_tasks) * 100, 1)
                    : 0;

                $isOverdue = $row->status === 'Pending' ? 'Yes' : 'No';

                fputcsv($handle, [
                    $row->date,
                    $row->staff_name,
                    $row->staff_email,
                    $this->formatRole($row->role),
                    $row->checklist_title,
                    $row->status,
                    $row->review_status ?? '-',
                    $row->total_tasks,
                    $row->completed_tasks,
                    $completionPct . '%',
                    $row->submitted_at ?? '-',
                    $row->reviewed_at ?? '-',
                    $row->reviewed_by ?? '-',
                    $row->manager_note ?? '-',
                    $isOverdue,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    /**
     * Get daily compliance summary
     *
     * Returns a summary of checklist compliance by date for charts/dashboards
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $fromDate = $validated['from_date'] ?? now()->subDays(30)->toDateString();
        $toDate = $validated['to_date'] ?? now()->toDateString();

        $summary = DB::table('checklist_instances')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->select(
                'date',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status IN ('Submitted') OR review_status = 'Approved' THEN 1 ELSE 0 END) as submitted"),
                DB::raw("SUM(CASE WHEN review_status = 'Approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'submitted' => (int) $row->submitted,
                'approved' => (int) $row->approved,
                'pending' => (int) $row->pending,
                'submission_rate' => $row->total > 0
                    ? round(($row->submitted / $row->total) * 100, 1)
                    : 0,
            ]);

        return response()->json([
            'data' => $summary,
            'meta' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    /**
     * Get role-wise compliance summary
     *
     * Returns compliance breakdown by staff role
     */
    public function roleSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $fromDate = $validated['from_date'] ?? now()->subDays(7)->toDateString();
        $toDate = $validated['to_date'] ?? now()->toDateString();

        $summary = DB::table('checklist_instances')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->select(
                'role',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status IN ('Submitted') OR review_status = 'Approved' THEN 1 ELSE 0 END) as submitted"),
                DB::raw("SUM(CASE WHEN review_status = 'Approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw('SUM(completed_tasks) as total_completed_tasks'),
                DB::raw('SUM(total_tasks) as total_task_items')
            )
            ->groupBy('role')
            ->orderBy('role')
            ->get()
            ->map(fn ($row) => [
                'role' => $row->role,
                'role_display' => $this->formatRole($row->role),
                'total' => (int) $row->total,
                'submitted' => (int) $row->submitted,
                'approved' => (int) $row->approved,
                'pending' => (int) $row->pending,
                'submission_rate' => $row->total > 0
                    ? round(($row->submitted / $row->total) * 100, 1)
                    : 0,
                'task_completion_rate' => $row->total_task_items > 0
                    ? round(($row->total_completed_tasks / $row->total_task_items) * 100, 1)
                    : 0,
            ]);

        return response()->json([
            'data' => $summary,
            'meta' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    /**
     * Get staff performance rankings
     *
     * Returns staff members ranked by checklist compliance
     */
    public function staffPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $fromDate = $validated['from_date'] ?? now()->subDays(30)->toDateString();
        $toDate = $validated['to_date'] ?? now()->toDateString();
        $limit = $validated['limit'] ?? 20;

        $performance = DB::table('checklist_instances as ci')
            ->join('users as u', 'ci.assignee_user_id', '=', 'u.id')
            ->where('ci.tenant_id', $tenantId)
            ->whereBetween('ci.date', [$fromDate, $toDate])
            ->select(
                'u.id as user_id',
                'u.name as user_name',
                'ci.role',
                DB::raw('COUNT(*) as total_checklists'),
                DB::raw("SUM(CASE WHEN ci.status IN ('Submitted') OR ci.review_status = 'Approved' THEN 1 ELSE 0 END) as submitted_count"),
                DB::raw("SUM(CASE WHEN ci.review_status = 'Approved' THEN 1 ELSE 0 END) as approved_count"),
                DB::raw('SUM(ci.completed_tasks) as total_completed_tasks'),
                DB::raw('SUM(ci.total_tasks) as total_task_items'),
                DB::raw('AVG(CASE WHEN ci.total_tasks > 0 THEN (ci.completed_tasks::float / ci.total_tasks) * 100 ELSE 0 END) as avg_task_completion')
            )
            ->groupBy('u.id', 'u.name', 'ci.role')
            ->orderByRaw('SUM(CASE WHEN ci.status IN (\'Submitted\') OR ci.review_status = \'Approved\' THEN 1 ELSE 0 END)::float / COUNT(*) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'role' => $row->role,
                'role_display' => $this->formatRole($row->role),
                'total_checklists' => (int) $row->total_checklists,
                'submitted_count' => (int) $row->submitted_count,
                'approved_count' => (int) $row->approved_count,
                'submission_rate' => $row->total_checklists > 0
                    ? round(($row->submitted_count / $row->total_checklists) * 100, 1)
                    : 0,
                'avg_task_completion' => round((float) $row->avg_task_completion, 1),
            ]);

        return response()->json([
            'data' => $performance,
            'meta' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    /**
     * Format role name for display
     */
    private function formatRole(string $role): string
    {
        return match ($role) {
            'CampusManager' => 'Campus Manager',
            'HKSupervisor' => 'HK Supervisor',
            'RMSupervisor' => 'RM Supervisor',
            'LaundryManager' => 'Laundry Manager',
            'SportsManager' => 'Sports Manager',
            default => $role,
        };
    }
}
