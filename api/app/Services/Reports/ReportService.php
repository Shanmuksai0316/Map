<?php

namespace App\Services\Reports;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\OutPass\Models\OutPass;
use App\Domain\Visitors\Models\GuestVisit;
use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Models\Hostel;
use App\Models\Incident;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centralized Report Service
 *
 * Generates report data for all roles. Each method returns a Collection of
 * associative arrays ready for CSV/XLSX export.
 *
 * Rules:
 * - Max time range: 30 days
 * - Format: CSV or XLSX only (no PDF)
 * - Small datasets (<1000 rows): instant download
 * - Large datasets (>=1000 rows): async via ExportJob queue
 */
class ReportService
{
    public const MAX_RANGE_DAYS = 30;

    public const THRESHOLD_ASYNC = 1000;

    /**
     * Validate date range (max 30 days).
     */
    public function validateDateRange(Carbon $from, Carbon $to): void
    {
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw new \InvalidArgumentException('Date range cannot exceed ' . self::MAX_RANGE_DAYS . ' days.');
        }

        if ($from->isAfter($to)) {
            throw new \InvalidArgumentException('Start date must be before end date.');
        }
    }

    // ─── SUPER ADMIN REPORTS ─────────────────────────────────────────

    public function tenantOverview(): Collection
    {
        return \App\Models\Tenant::withCount(['hostels', 'users'])
            ->get()
            ->map(fn ($t) => [
                'Tenant Code' => $t->code,
                'Tenant Name' => $t->name,
                'Status' => $t->status->value ?? $t->status,
                'Hostels' => $t->hostels_count,
                'Students' => Student::where('tenant_id', $t->id)->count(),
                'Staff' => User::where('tenant_id', $t->id)->where('kind', '!=', 'student')->count(),
            ]);
    }

    public function occupancyReport(?string $tenantId = null): Collection
    {
        $query = Hostel::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        return $query->get()->map(function ($hostel) {
            $totalBeds = RoomBed::where('hostel_id', $hostel->id)->count();
            $occupied = RoomBed::where('hostel_id', $hostel->id)->where('status', 'occupied')->count();
            $available = $totalBeds - $occupied;

            return [
                'Tenant' => $hostel->tenant?->name ?? '—',
                'Hostel' => $hostel->name,
                'Gender Mode' => ucfirst($hostel->gender_mode),
                'Total Beds' => $totalBeds,
                'Occupied' => $occupied,
                'Available' => $available,
                'Occupancy %' => $totalBeds > 0 ? round(($occupied / $totalBeds) * 100, 1) . '%' : '0%',
            ];
        });
    }

    public function staffDeployment(?string $tenantId = null): Collection
    {
        $query = DB::table('staff_assignments as sa')
            ->join('users as u', 'sa.user_id', '=', 'u.id')
            ->join('hostels as h', 'sa.hostel_id', '=', 'h.id')
            ->leftJoin('tenants as t', 'sa.tenant_id', '=', 't.id')
            ->leftJoin('model_has_roles as mhr', function ($join) {
                $join->on('sa.user_id', '=', 'mhr.model_id')
                    ->where('mhr.model_type', '=', 'App\\Models\\User');
            })
            ->leftJoin('roles as r', 'mhr.role_id', '=', 'r.id')
            ->whereNull('sa.revoked_at')
            ->when($tenantId, fn ($q) => $q->where('sa.tenant_id', $tenantId))
            ->select('u.name as staff_name', 'u.phone', 't.name as tenant_name', 'h.name as hostel_name', 'r.name as role', 'sa.assigned_at')
            ->orderBy('t.name')
            ->orderBy('h.name');

        return collect($query->get())->map(fn ($row) => [
            'Staff Name' => $row->staff_name,
            'Phone' => $row->phone,
            'Tenant' => $row->tenant_name,
            'Hostel' => $row->hostel_name,
            'Role' => $row->role,
            'Assigned Since' => $row->assigned_at ? Carbon::parse($row->assigned_at)->format('d M Y') : '—',
        ]);
    }

    public function requestSummary(?string $tenantId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        $tickets = Ticket::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->with(['hostel'])
            ->get()
            ->map(fn ($t) => [
                'Date' => $t->created_at?->format('d M Y') ?? '—',
                'Request Type' => 'Ticket',
                'Reference' => 'TKT-' . $t->id,
                'Tenant' => $t->tenant?->name ?? '—',
                'Hostel' => $t->hostel?->name ?? '—',
                'Category' => $t->category ?? '—',
                'Status' => $t->status ?? '—',
                'Priority' => $t->priority ?? '—',
            ]);

        $outPasses = OutPass::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->with(['student.user', 'hostel'])
            ->get()
            ->map(fn ($op) => [
                'Date' => $op->created_at?->format('d M Y') ?? '—',
                'Request Type' => 'OutPass/Leave',
                'Reference' => 'OP-' . $op->id,
                'Tenant' => $op->tenant?->name ?? '—',
                'Hostel' => $op->hostel?->name ?? '—',
                'Category' => $op->overnight ? 'Overnight' : 'Day',
                'Status' => $op->status ?? '—',
                'Priority' => '—',
            ]);

        return $tickets->concat($outPasses)->sortByDesc('Date')->values();
    }

    public function checkoutRenewal(?string $tenantId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return RoomAllocation::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('updated_at', [$from, $to])
            ->with(['tenant', 'student.user', 'hostel', 'roomBed'])
            ->get()
            ->map(fn ($alloc) => [
                'Date' => $alloc->updated_at?->format('d M Y') ?? '—',
                'Tenant' => $alloc->tenant?->name ?? '—',
                'Student' => $alloc->student?->user?->name ?? '—',
                'Hostel' => $alloc->hostel?->name ?? '—',
                'Bed ID' => $alloc->roomBed?->id ?? '—',
                'Expected Checkout' => $alloc->expected_checkout_at?->format('d M Y') ?? '—',
                'Checkout Status' => $alloc->checkout_status ?? '—',
                'Active Allocation' => $alloc->is_active ? 'Yes' : 'No',
            ]);
    }

    public function paymentCollections(?string $tenantId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return collect(
            DB::table('payments as p')
                ->leftJoin('students as s', 's.id', '=', 'p.student_id')
                ->leftJoin('users as u', 'u.id', '=', 's.user_id')
                ->leftJoin('tenants as t', 't.id', '=', 's.tenant_id')
                ->when($tenantId, fn ($q) => $q->where('s.tenant_id', $tenantId))
                ->whereBetween('p.created_at', [$from, $to])
                ->orderByDesc('p.created_at')
                ->select('p.created_at', 't.name as tenant_name', 'u.name as student_name', 'p.reference', 'p.amount_paise', 'p.currency', 'p.mode', 'p.status')
                ->get()
        )->map(fn ($row) => [
            'Date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '—',
            'Tenant' => $row->tenant_name ?? '—',
            'Student' => $row->student_name ?? '—',
            'Reference' => $row->reference ?? '—',
            'Amount' => isset($row->amount_paise) ? number_format($row->amount_paise / 100, 2) : '0.00',
            'Currency' => $row->currency ?? 'INR',
            'Mode' => $row->mode ?? '—',
            'Status' => $row->status ?? '—',
        ]);
    }

    public function auditTrail(?string $tenantId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return collect(
            DB::table('audit_logs as al')
                ->leftJoin('users as u', 'u.id', '=', 'al.user_id')
                ->leftJoin('tenants as t', 't.id', '=', 'al.tenant_id')
                ->when($tenantId, fn ($q) => $q->where('al.tenant_id', $tenantId))
                ->whereBetween('al.created_at', [$from, $to])
                ->orderByDesc('al.created_at')
                ->select('al.created_at', 't.name as tenant_name', 'u.name as user_name', 'al.action', 'al.auditable_type', 'al.auditable_id')
                ->limit(5000)
                ->get()
        )->map(fn ($row) => [
            'DateTime' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '—',
            'Tenant' => $row->tenant_name ?? '—',
            'User' => $row->user_name ?? 'System',
            'Action' => $row->action ?? '—',
            'Entity Type' => $row->auditable_type ? class_basename($row->auditable_type) : '—',
            'Entity ID' => $row->auditable_id ?? '—',
        ]);
    }

    // ─── CAMPUS MANAGER REPORTS ──────────────────────────────────────

    public function housekeepingRequests(string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return Ticket::where('tenant_id', $tenantId)
            ->where('category', 'housekeeping')
            ->whereBetween('created_at', [$from, $to])
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->get()
            ->map(fn ($t) => [
                'Date' => $t->created_at->format('d M Y'),
                'Ticket ID' => $t->id,
                'Title' => $t->title,
                'Priority' => $t->priority,
                'Status' => $t->status,
                'Assigned To' => $t->assignee?->name ?? '—',
                'Created By' => $t->creator?->name ?? '—',
                'Resolved At' => $t->resolved_at?->format('d M Y H:i') ?? '—',
            ]);
    }

    public function maintenanceRequests(string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return Ticket::where('tenant_id', $tenantId)
            ->where('category', 'maintenance')
            ->whereBetween('created_at', [$from, $to])
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->get()
            ->map(fn ($t) => [
                'Date' => $t->created_at->format('d M Y'),
                'Ticket ID' => $t->id,
                'Title' => $t->title,
                'Priority' => $t->priority,
                'Status' => $t->status,
                'Assigned To' => $t->assignee?->name ?? '—',
                'Created By' => $t->creator?->name ?? '—',
                'Resolved At' => $t->resolved_at?->format('d M Y H:i') ?? '—',
            ]);
    }

    public function passRequests(?string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return OutPass::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->with(['student.user'])
            ->get()
            ->map(fn ($op) => [
                'Date' => $op->created_at->format('d M Y'),
                'Student' => $op->student?->user?->name ?? '—',
                'Type' => $op->overnight ? 'Overnight' : 'Day',
                'Reason' => $op->reason,
                'Status' => $op->status,
                'Decided By' => $op->decided_by ? User::find($op->decided_by)?->name : '—',
                'Valid Until' => $op->valid_until?->format('d M Y H:i') ?? '—',
            ]);
    }

    public function attendanceSummary(?string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return AttendanceSession::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->with('hostel')
            ->get()
            ->map(function ($session) {
                $present = AttendanceLog::where('attendance_session_id', $session->id)->where('status', 'present')->count();
                $absent = AttendanceLog::where('attendance_session_id', $session->id)->where('status', 'absent')->count();
                $leave = AttendanceLog::where('attendance_session_id', $session->id)->where('status', 'leave')->count();
                $total = $present + $absent + $leave;

                return [
                    'Date' => Carbon::parse($session->session_date)->format('d M Y'),
                    'Hostel' => $session->hostel?->name ?? '—',
                    'Session Type' => $session->kind ?? 'Roll Call',
                    'Total' => $total,
                    'Present' => $present,
                    'Absent' => $absent,
                    'On Leave' => $leave,
                    'Attendance %' => $total > 0 ? round(($present / $total) * 100, 1) . '%' : '0%',
                    'Status' => $session->status,
                ];
            });
    }

    public function checklistCompliance(?string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return ChecklistInstance::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->with(['template', 'items'])
            ->get()
            ->map(function ($instance) {
                $total = $instance->items->count();
                $completed = $instance->items->where('status', 'done')->count();
                $user = User::find($instance->assigned_to ?? $instance->created_by);

                return [
                    'Date' => $instance->created_at->format('d M Y'),
                    'Staff Name' => $user?->name ?? '—',
                    'Role' => $user?->roles->first()?->name ?? '—',
                    'Checklist' => $instance->template?->name ?? '—',
                    'Total Items' => $total,
                    'Completed' => $completed,
                    'Completion %' => $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%',
                    'Status' => $instance->status,
                    'Submitted At' => $instance->completed_at?->format('d M Y H:i') ?? '—',
                ];
            });
    }

    public function roomOccupancy(?string $tenantId, ?int $hostelId): Collection
    {
        return Room::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->with(['hostel', 'beds'])
            ->orderBy('hostel_id')
            ->orderBy('number')
            ->get()
            ->map(function ($room) {
                $occupied = $room->beds->where('status', 'occupied')->count();
                $available = $room->beds->where('status', 'available')->count();

                return [
                    'Hostel' => $room->hostel?->name ?? '—',
                    'Floor' => $room->floor_code,
                    'Room' => $room->number,
                    'Type' => $room->room_type,
                    'Capacity' => $room->capacity,
                    'Occupied' => $occupied,
                    'Available' => $available,
                ];
            });
    }

    public function guestVisitLog(?string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return GuestVisit::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->with(['visitor', 'student.user'])
            ->get()
            ->map(fn ($gv) => [
                'Date' => $gv->created_at->format('d M Y'),
                'Visitor' => $gv->visitor?->name ?? '—',
                'Student Host' => $gv->student?->user?->name ?? '—',
                'Visit Date' => $gv->visit_date?->format('d M Y') ?? '—',
                'Status' => $gv->status,
            ]);
    }

    public function incidentSummary(?string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return Incident::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->with(['hostel'])
            ->get()
            ->map(fn ($inc) => [
                'Date' => $inc->created_at->format('d M Y'),
                'Hostel' => $inc->hostel?->name ?? '—',
                'Type' => $inc->type,
                'Severity' => $inc->severity ?? '—',
                'Status' => $inc->status ?? '—',
                'Reported By' => User::find($inc->reported_by)?->name ?? '—',
            ]);
    }

    // ─── RECTOR REPORTS ──────────────────────────────────────────────

    public function approvalSummary(?string $tenantId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        return OutPass::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['approved', 'declined', 'expired'])
            ->with(['student.user'])
            ->get()
            ->map(function ($op) {
                $decisionTime = $op->decided_at && $op->created_at
                    ? $op->created_at->diffInMinutes($op->decided_at) . ' min'
                    : '—';

                return [
                    'Date' => $op->created_at->format('d M Y'),
                    'Student' => $op->student?->user?->name ?? '—',
                    'Type' => $op->overnight ? 'Overnight' : 'Day',
                    'Status' => $op->status,
                    'Decision Time' => $decisionTime,
                    'SLA Breach' => $op->decided_at && $op->created_at && $op->created_at->diffInHours($op->decided_at) > 2 ? 'Yes' : 'No',
                    'Decided By' => $op->decided_by ? User::find($op->decided_by)?->name : '—',
                ];
            });
    }

    // ─── ATTENDANCE REPORT WITH STUDENT DETAILS ──────────────────────

    public function attendanceDetailReport(?string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): Collection
    {
        $this->validateDateRange($from, $to);

        $sessions = AttendanceSession::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->with('hostel')
            ->get();

        $rows = collect();

        foreach ($sessions as $session) {
            $logs = AttendanceLog::where('attendance_session_id', $session->id)
                ->with(['student.user'])
                ->get();

            $present = $logs->where('status', 'present')->count();
            $absent = $logs->where('status', 'absent')->count();
            $leave = $logs->where('status', 'leave')->count();
            $total = $present + $absent + $leave;

            $absentNames = $logs->where('status', 'absent')
                ->map(fn ($log) => $log->student?->user?->name ?? $log->student?->full_name ?? 'Unknown')
                ->filter()
                ->implode(', ');

            $absentIds = $logs->where('status', 'absent')
                ->map(fn ($log) => $log->student?->student_uid ?? '—')
                ->filter()
                ->implode(', ');

            $rows->push([
                'Date' => Carbon::parse($session->session_date)->format('d M Y'),
                'Hostel' => $session->hostel?->name ?? '—',
                'Session Type' => $session->kind ?? 'Roll Call',
                'Total Students' => $total,
                'Present' => $present,
                'Absent' => $absent,
                'On Leave' => $leave,
                'Attendance %' => $total > 0 ? round(($present / $total) * 100, 1) . '%' : '0%',
                'Status' => $session->status,
                'Absent Student Names' => $absentNames ?: '—',
                'Absent Student IDs' => $absentIds ?: '—',
            ]);
        }

        return $rows;
    }
}
