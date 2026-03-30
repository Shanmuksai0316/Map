<?php

namespace App\Support\Dashboard;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Gate\Models\GateDevice;
use App\Domain\Gate\Models\GateEntry;
use App\Models\Domain\OutPass\OutPass;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KpisRepository
{
    public function occupancy(string|int|null $tenantId, array $hostelIds = []): array
    {
        $cacheKey = 'dash:occ:' . ($tenantId ?? 'all') . ':' . md5(json_encode($hostelIds));
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $hostelIds) {
            // Handle Super Admin cross-tenant queries (null tenant_id)
            if ($tenantId === null) {
                $bedsQuery = RoomBed::query();
                if (!empty($hostelIds)) {
                    $bedsQuery->whereIn('hostel_id', $hostelIds);
                }
                $totalBeds = $bedsQuery->count();

                $occupiedQuery = RoomAllocation::query()->where('is_active', true);
                if (!empty($hostelIds)) {
                    $occupiedQuery->whereIn('hostel_id', $hostelIds);
                }
                $occupied = $occupiedQuery->count();
                $available = max(0, $totalBeds - $occupied);
                $utilization = $totalBeds > 0 ? round(($occupied / $totalBeds) * 100, 1) : 0;

                return [
                    'total' => $totalBeds,
                    'occupied' => $occupied,
                    'available' => $available,
                    'utilization' => $utilization,
                ];
            }
            
            // Single tenant query
            $bedsQuery = RoomBed::query()
                ->where('tenant_id', $tenantId);

            if (!empty($hostelIds)) {
                $bedsQuery->whereIn('hostel_id', $hostelIds);
            }

            $totalBeds = $bedsQuery->count();

            $occupiedQuery = RoomAllocation::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true);

            if (!empty($hostelIds)) {
                $occupiedQuery->whereIn('hostel_id', $hostelIds);
            }

            $occupied = $occupiedQuery->count();
            $available = max(0, $totalBeds - $occupied);
            $utilization = $totalBeds > 0 ? round(($occupied / $totalBeds) * 100, 1) : 0;

            return [
                'total' => $totalBeds,
                'occupied' => $occupied,
                'available' => $available,
                'utilization' => $utilization,
            ];
        });
    }

    public function outPassDailyCounts(int $tenantId, int $days = 14, array $hostelIds = []): array
    {
        $cacheKey = 'dash:outpass:' . $tenantId . ':' . $days . ':' . md5(json_encode($hostelIds));
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $days, $hostelIds) {
            $dates = DateRange::lastNDays($days);
            $startDate = Carbon::parse($dates[0]);
            $endDate = Carbon::parse($dates[count($dates) - 1]);

            $query = OutPass::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereBetween(DB::raw('DATE(requested_at)'), [$startDate, $endDate]);

            if (!empty($hostelIds)) {
                $query->whereIn('hostel_id', $hostelIds);
            }

            $counts = $query
                ->select(DB::raw('DATE(requested_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $data = [];
            foreach ($dates as $date) {
                $data[$date] = $counts[$date] ?? 0;
            }

            return $data;
        });
    }

    public function lateReturnSplit(int $tenantId, int $days = 7, array $hostelIds = []): array
    {
        $cacheKey = 'dash:late:' . $tenantId . ':' . $days . ':' . md5(json_encode($hostelIds));
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $days, $hostelIds) {
            $startDate = Carbon::today()->subDays($days - 1);

            $query = GateEntry::query()
                ->where('tenant_id', $tenantId)
                ->where('direction', 'in')
                ->where('created_at', '>=', $startDate);

            if (!empty($hostelIds)) {
                $query->whereIn('hostel_id', $hostelIds);
            }

            $total = $query->count();
            $late = $query->where('late_minutes', '>', 0)->count();
            $onTime = $total - $late;

            return [
                'on_time' => $onTime,
                'late' => $late,
            ];
        });
    }

    public function attendanceCompliance(int $tenantId, int $days = 7, array $hostelIds = []): array
    {
        $cacheKey = 'dash:att:' . $tenantId . ':' . $days . ':' . md5(json_encode($hostelIds));
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $days, $hostelIds) {
            $dates = DateRange::lastNDays($days);
            $data = [];

            foreach ($dates as $date) {
                $sessionsQuery = AttendanceSession::query()
                    ->where('tenant_id', $tenantId)
                    ->whereDate('scheduled_at', $date);

                if (!empty($hostelIds)) {
                    $sessionsQuery->whereIn('hostel_id', $hostelIds);
                }

                $totalSessions = $sessionsQuery->count();
                $closedSessions = $sessionsQuery->where('status', 'closed')->count();

                $compliance = $totalSessions > 0 ? round(($closedSessions / $totalSessions) * 100, 1) : 0;
                $data[$date] = $compliance;
            }

            return $data;
        });
    }

    public function checklistCompletionByRole(int $tenantId, Carbon $date, array $hostelIds = []): array
    {
        $cacheKey = 'dash:checklist:' . $tenantId . ':' . $date->toDateString() . ':' . md5(json_encode($hostelIds));
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $date, $hostelIds) {
            $query = ChecklistInstance::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('date', $date);

            if (!empty($hostelIds)) {
                $query->whereIn('hostel_id', $hostelIds);
            }

            $instances = $query->get();
            
            $roleStats = [];
            
            foreach (['Warden', 'HK Supervisor', 'RM Supervisor'] as $role) {
                $roleInstances = $instances->filter(function ($instance) use ($role) {
                    // Simplified: assume we can determine role from template or metadata
                    return true; // For now, group all together
                });
                
                $total = $roleInstances->count();
                $approved = $roleInstances->where('status', 'approved')->count();
                
                $completion = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
                $roleStats[$role] = $completion;
            }

            return $roleStats;
        });
    }

    public function ticketSlaSplit(int $tenantId, array $hostelIds = []): array
    {
        $cacheKey = 'dash:tickets:' . $tenantId . ':' . md5(json_encode($hostelIds));
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $hostelIds) {
            $query = Ticket::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'open');

            if (!empty($hostelIds)) {
                $query->whereIn('hostel_id', $hostelIds);
            }

            $tickets = $query->get();
            
            $onTime = 0;
            $breached = 0;
            $now = Carbon::now();

            foreach ($tickets as $ticket) {
                $age = $ticket->created_at->diffInHours($now);
                
                // Simple SLA: 4h for first response
                if ($age <= 4) {
                    $onTime++;
                } else {
                    $breached++;
                }
            }

            return [
                'on_time' => $onTime,
                'breached' => $breached,
            ];
        });
    }

    public function devicesHealth(int $tenantId, array $hostelIds = []): array
    {
        $cacheKey = 'dash:devices:' . $tenantId . ':' . md5(json_encode($hostelIds));
        
        return Cache::remember($cacheKey, 60, function () use ($tenantId, $hostelIds) {
            $query = GateDevice::query()
                ->where('tenant_id', $tenantId);

            if (!empty($hostelIds)) {
                $query->whereIn('hostel_id', $hostelIds);
            }

            $total = $query->count();
            $tenMinutesAgo = Carbon::now()->subMinutes(10);
            
            $active = $query->where('is_active', true)
                ->where('last_seen_at', '>=', $tenMinutesAgo)
                ->count();
            
            $stale = $total - $active;

            return [
                'total' => $total,
                'active' => $active,
                'stale' => $stale,
            ];
        });
    }

    // Super Admin KPIs
    /**
     * @param  string|int|null  $tenantId
     */
    public function totalHostels(string|int|null $tenantId): int
    {
        if ($tenantId === null) {
            return \App\Models\Hostel::count();
        }
        return \App\Models\Hostel::where('tenant_id', $tenantId)->count();
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function bedsUtilizationPercent(string|int|null $tenantId): float
    {
        if ($tenantId === null) {
            $occupancy = $this->occupancy(null);
            return $occupancy['total'] > 0 ? round(($occupancy['occupied'] / $occupancy['total']) * 100, 1) : 0;
        }
        $occupancy = $this->occupancy($tenantId);
        return $occupancy['utilization'];
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function availableBeds(string|int|null $tenantId): int
    {
        if ($tenantId === null) {
            $occupancy = $this->occupancy(null);
            return max(0, $occupancy['available']);
        }
        $occupancy = $this->occupancy($tenantId);
        return max(0, $occupancy['available']);
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function outPassesToday(string|int|null $tenantId): array
    {
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        
        if ($tenantId === null) {
            $total = OutPass::whereBetween('created_at', [$today, $tomorrow])->count();
            $approved = OutPass::whereBetween('created_at', [$today, $tomorrow])->where('status', 'approved')->count();
            $pending = OutPass::whereBetween('created_at', [$today, $tomorrow])->where('status', 'pending')->count();
            return ['total' => $total, 'approved' => $approved, 'pending' => $pending];
        }
        
        $total = OutPass::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$today, $tomorrow])
            ->count();
            
        $approved = OutPass::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$today, $tomorrow])
            ->where('status', 'approved')
            ->count();
            
        $pending = OutPass::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$today, $tomorrow])
            ->where('status', 'pending')
            ->count();
            
        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
        ];
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function lateReturnsToday(string|int|null $tenantId): int
    {
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        
        if ($tenantId === null) {
            $total = OutPass::whereBetween('created_at', [$today, $tomorrow])
                ->where('status', 'approved')
                ->where('expected_return', '<', now())
                ->whereNull('returned_at')
                ->count();
            return max(0, $total);
        }
        
        return max(0, OutPass::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$today, $tomorrow])
            ->where('status', 'approved')
            ->where('expected_return', '<', now())
            ->whereNull('returned_at')
            ->count());
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function ticketsOpenByPriority(string|int|null $tenantId): array
    {
        $priorities = ['high', 'medium', 'low', 'urgent'];
        $counts = [];

        foreach ($priorities as $priority) {
            $counts[$priority] = $this->countOpenTicketsByPriority($priority, $tenantId);
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * @param string $priority
     * @param string|int|null $tenantId
     */
    private function countOpenTicketsByPriority(string $priority, string|int|null $tenantId): int
    {
        if ($tenantId === null) {
            $total = 0;
            \Tenancy::all()->each(function ($tenant) use (&$total, $priority) {
                $tenant->run(function () use (&$total, $priority) {
                    $total += Ticket::where('status', 'open')->where('priority', $priority)->count();
                });
            });
            return $total;
        }

        return max(0, Ticket::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->where('priority', $priority)
            ->count());
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function ticketsSlaBreachedOpen(string|int|null $tenantId): int
    {
        if ($tenantId === null) {
            return Ticket::where('status', 'open')->where('sla_deadline', '<', now())->count();
        }
        
        return max(0, Ticket::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->where('sla_deadline', '<', now())
            ->count());
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function attendanceClosure7dPercent(string|int|null $tenantId): float
    {
        $sevenDaysAgo = now()->subDays(7)->startOfDay();
        
        if ($tenantId === null) {
            $totalSessions = AttendanceSession::where('created_at', '>=', $sevenDaysAgo)->count();
            $closedSessions = AttendanceSession::where('created_at', '>=', $sevenDaysAgo)->whereNotNull('closed_at')->count();
            return $totalSessions > 0 ? round(($closedSessions / $totalSessions) * 100, 1) : 0;
        }
        
        $totalSessions = AttendanceSession::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();
            
        $closedSessions = AttendanceSession::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->whereNotNull('closed_at')
            ->count();
            
        return $totalSessions > 0 ? round(($closedSessions / $totalSessions) * 100, 1) : 0;
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function checklistOnTime7dPercent(string|int|null $tenantId): float
    {
        $sevenDaysAgo = now()->subDays(7)->startOfDay();
        
        if ($tenantId === null) {
            $totalInstances = ChecklistInstance::where('created_at', '>=', $sevenDaysAgo)->count();
            $onTimeInstances = ChecklistInstance::where('created_at', '>=', $sevenDaysAgo)
                ->whereNotNull('completed_at')
                ->whereNotNull('due_at')
                ->whereColumn('completed_at', '<=', 'due_at')
                ->count();
            return $totalInstances > 0 ? round(($onTimeInstances / $totalInstances) * 100, 1) : 0;
        }
        
        $totalInstances = ChecklistInstance::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();
            
        $onTimeInstances = ChecklistInstance::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->whereNotNull('completed_at')
            ->whereNotNull('due_at')
            ->whereColumn('completed_at', '<=', 'due_at')
            ->count();
            
        return $totalInstances > 0 ? round(($onTimeInstances / $totalInstances) * 100, 1) : 0;
    }

    /**
     * @param  string|int|null  $tenantId
     */
    public function deviceHealth(string|int|null $tenantId): array
    {
        if ($tenantId === null) {
            $total = GateDevice::count();
            $active = GateDevice::where('last_seen_at', '>', now()->subMinutes(10))->count();
            return ['total' => $total, 'active' => $active, 'stale' => $total - $active];
        }
        
        $total = GateDevice::where('tenant_id', $tenantId)->count();
        $active = GateDevice::where('tenant_id', $tenantId)
            ->where('last_seen_at', '>', now()->subMinutes(10))
            ->count();
        $stale = $total - $active;
        
        return [
            'total' => $total,
            'active' => $active,
            'stale' => $stale,
        ];
    }

    /**
     * @param  string|int  $tenantId
     */
    public function attendanceClosure7dTrend(string|int $tenantId): array
    {
        $percentages = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = $date->copy()->addDay();
            
            $totalSessions = AttendanceSession::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$date, $nextDate])
                ->count();
                
            $closedSessions = AttendanceSession::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$date, $nextDate])
                ->whereNotNull('closed_at')
                ->count();
                
            $percentages[] = (float) ($totalSessions > 0 ? round(($closedSessions / $totalSessions) * 100, 1) : 0.0);
        }
        
        return $percentages;
    }
}

