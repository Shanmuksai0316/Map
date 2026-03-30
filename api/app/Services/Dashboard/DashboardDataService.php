<?php

namespace App\Services\Dashboard;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\OutPass\Models\OutPass;
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
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Data Service
 *
 * Provides data for Chart.js visualizations across all dashboards.
 * Each method returns arrays ready for Chart.js configuration.
 */
class DashboardDataService
{
    // ─── SHARED / COMMON ─────────────────────────────────────────

    /**
     * Occupancy donut chart data.
     */
    public function occupancyByHostel(?string $tenantId = null, ?int $hostelId = null): array
    {
        $query = Hostel::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($hostelId, fn ($q) => $q->where('id', $hostelId));

        $hostels = $query->get();
        $labels = [];
        $occupied = [];
        $available = [];

        foreach ($hostels as $hostel) {
            $labels[] = $hostel->name;
            $total = RoomBed::where('hostel_id', $hostel->id)->count();
            $occ = RoomBed::where('hostel_id', $hostel->id)->where('status', 'occupied')->count();
            $occupied[] = $occ;
            $available[] = $total - $occ;
        }

        return compact('labels', 'occupied', 'available');
    }

    /**
     * Student count by hostel (bar chart).
     */
    public function studentsByHostel(?string $tenantId = null): array
    {
        $hostels = Hostel::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->withCount('students')
            ->get();

        return [
            'labels' => $hostels->pluck('name')->toArray(),
            'data' => $hostels->pluck('students_count')->toArray(),
        ];
    }

    /**
     * Request status breakdown (stacked bar / pie chart).
     */
    public function requestsByStatus(string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): array
    {
        $categories = ['housekeeping', 'maintenance'];
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $datasets = [];

        foreach ($categories as $category) {
            $counts = [];
            foreach ($statuses as $status) {
                $counts[] = Ticket::where('tenant_id', $tenantId)
                    ->where('category', $category)
                    ->where('status', $status)
                    ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
                    ->whereBetween('created_at', [$from, $to])
                    ->count();
            }
            $datasets[$category] = $counts;
        }

        return [
            'labels' => array_map('ucfirst', $statuses),
            'datasets' => $datasets,
        ];
    }

    /**
     * Daily attendance trend (line chart).
     */
    public function attendanceTrend(string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): array
    {
        $dates = [];
        $percentages = [];

        $current = $from->copy();
        while ($current <= $to) {
            $dateStr = $current->toDateString();
            $dates[] = $current->format('d M');

            $sessions = AttendanceSession::where('tenant_id', $tenantId)
                ->whereDate('session_date', $dateStr)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
                ->pluck('id');

            if ($sessions->isEmpty()) {
                $percentages[] = null;
            } else {
                $total = AttendanceLog::whereIn('attendance_session_id', $sessions)->count();
                $present = AttendanceLog::whereIn('attendance_session_id', $sessions)->where('status', 'present')->count();
                $percentages[] = $total > 0 ? round(($present / $total) * 100, 1) : 0;
            }

            $current->addDay();
        }

        return [
            'labels' => $dates,
            'data' => $percentages,
        ];
    }

    /**
     * Pass request trend (line chart).
     */
    public function passRequestTrend(string $tenantId, ?int $hostelId, Carbon $from, Carbon $to): array
    {
        $dates = [];
        $approved = [];
        $declined = [];
        $pending = [];

        $current = $from->copy();
        while ($current <= $to) {
            $dates[] = $current->format('d M');

            $baseQ = OutPass::where('tenant_id', $tenantId)
                ->whereDate('created_at', $current)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId));

            $approved[] = (clone $baseQ)->where('status', 'approved')->count();
            $declined[] = (clone $baseQ)->where('status', 'declined')->count();
            $pending[] = (clone $baseQ)->where('status', 'pending')->count();

            $current->addDay();
        }

        return [
            'labels' => $dates,
            'approved' => $approved,
            'declined' => $declined,
            'pending' => $pending,
        ];
    }

    /**
     * Checklist compliance trend (line chart).
     */
    public function checklistTrend(string $tenantId, Carbon $from, Carbon $to): array
    {
        $dates = [];
        $completionRates = [];

        $current = $from->copy();
        while ($current <= $to) {
            $dates[] = $current->format('d M');

            $instances = ChecklistInstance::where('tenant_id', $tenantId)
                ->whereDate('created_at', $current)
                ->with('items')
                ->get();

            if ($instances->isEmpty()) {
                $completionRates[] = null;
            } else {
                $totalItems = $instances->sum(fn ($i) => $i->items->count());
                $doneItems = $instances->sum(fn ($i) => $i->items->where('status', 'done')->count());
                $completionRates[] = $totalItems > 0 ? round(($doneItems / $totalItems) * 100, 1) : 0;
            }

            $current->addDay();
        }

        return [
            'labels' => $dates,
            'data' => $completionRates,
        ];
    }

    /**
     * Checkout timeline (bar chart).
     */
    public function checkoutTimeline(string $tenantId, ?int $hostelId): array
    {
        $ranges = [
            'Overdue' => [null, now()],
            'Due Today' => [now()->startOfDay(), now()->endOfDay()],
            'This Week' => [now()->addDay(), now()->addWeek()],
            'This Month' => [now()->addWeek(), now()->addMonth()],
            '2-3 Months' => [now()->addMonth(), now()->addMonths(3)],
            '3+ Months' => [now()->addMonths(3), now()->addYear()],
        ];

        $labels = [];
        $counts = [];

        foreach ($ranges as $label => [$start, $end]) {
            $labels[] = $label;

            $q = RoomAllocation::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId));

            if ($label === 'Overdue') {
                $counts[] = (clone $q)->whereNotNull('expected_checkout_at')
                    ->where('expected_checkout_at', '<', now())
                    ->count();
            } else {
                $counts[] = (clone $q)->whereNotNull('expected_checkout_at')
                    ->whereBetween('expected_checkout_at', [$start, $end])
                    ->count();
            }
        }

        return compact('labels', 'counts');
    }

    // ─── SUPER ADMIN SPECIFIC ────────────────────────────────────

    /**
     * Tenant-level student count (horizontal bar chart).
     */
    public function studentsByTenant(): array
    {
        $tenants = \App\Models\Tenant::query()
            ->where('status', 'active')
            ->get()
            ->map(fn ($t) => [
                'name' => $t->name,
                'count' => Student::where('tenant_id', $t->id)->count(),
            ]);

        return [
            'labels' => $tenants->pluck('name')->toArray(),
            'data' => $tenants->pluck('count')->toArray(),
        ];
    }

    // ─── SCORECARD METRICS ───────────────────────────────────────

    /**
     * Quick stats for Campus Manager dashboard.
     */
    public function campusManagerStats(string $tenantId, ?int $hostelId): array
    {
        return [
            'total_students' => Student::where('tenant_id', $tenantId)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))->count(),
            'active_allocations' => RoomAllocation::where('tenant_id', $tenantId)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
                ->where('is_active', true)->count(),
            'pending_requests' => Ticket::where('tenant_id', $tenantId)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
                ->whereIn('status', ['open', 'in_progress'])->count(),
            'pending_passes' => OutPass::where('tenant_id', $tenantId)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
                ->where('status', 'pending')->count(),
            'due_checkout_30d' => RoomAllocation::where('tenant_id', $tenantId)
                ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
                ->where('is_active', true)
                ->whereNotNull('expected_checkout_at')
                ->whereBetween('expected_checkout_at', [now(), now()->addDays(30)])->count(),
        ];
    }
}
