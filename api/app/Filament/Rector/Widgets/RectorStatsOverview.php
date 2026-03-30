<?php

namespace App\Filament\Rector\Widgets;

use App\Enums\OutPassStatus;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Incident;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RectorStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $cacheKey = 'rector_stats_' . $user->id . '_' . $tenantId;
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $user) {
            try {
                // Get rector's assigned hostel IDs from staff_assignments
                $assignedHostelIds = DB::table('staff_assignments')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('revoked_at')
                    ->whereNotNull('hostel_id')
                    ->pluck('hostel_id')
                    ->toArray();

                // If no hostels assigned, use all hostels in tenant (fallback)
                if (empty($assignedHostelIds)) {
                    $assignedHostelIds = Hostel::where('tenant_id', $tenantId)
                        ->pluck('id')
                        ->toArray();
                }

                // Active hostels (tenant-scoped and hostel-scoped)
                $hostelCount = Hostel::where('tenant_id', $tenantId)
                    ->whereIn('id', $assignedHostelIds)
                    ->count();
                
                // Resident students (only in assigned hostels)
                $studentCount = Student::where('tenant_id', $tenantId)
                    ->whereHas('roomAllocations', function ($q) use ($assignedHostelIds) {
                        $q->where('is_active', true)
                            ->whereHas('roomBed.room.hostel', function ($q2) use ($assignedHostelIds) {
                                $q2->whereIn('id', $assignedHostelIds);
                            });
                    })
                    ->count();
                
                // Pending requests (Out-Pass + Leave + Sick Leave) in assigned hostels
                $pendingOutPass = OutPass::where('tenant_id', $tenantId)
                    ->where('status', OutPassStatus::PENDING)
                    ->whereIn('hostel_id', $assignedHostelIds)
                    ->count();
                
                $pendingLeave = DB::table('leaves')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->whereIn('hostel_id', $assignedHostelIds)
                    ->count();
                
                $pendingSickLeave = DB::table('sick_leaves')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->whereIn('hostel_id', $assignedHostelIds)
                    ->count();
                
                $pendingApprovals = $pendingOutPass + $pendingLeave + $pendingSickLeave;

                // Return only 3 cards as per UI requirements
                return [
                    Stat::make('Active Hostels', number_format($hostelCount))
                        ->description('Under your oversight')
                        ->descriptionIcon('heroicon-m-building-office')
                        ->color('primary'),
                    Stat::make('Resident Students', number_format($studentCount))
                        ->description('Across all assigned hostels')
                        ->descriptionIcon('heroicon-m-users')
                        ->color('success'),
                    Stat::make('Pending Requests', number_format($pendingApprovals))
                        ->description('Awaiting your decision')
                        ->descriptionIcon('heroicon-m-clock')
                        ->color($pendingApprovals > 0 ? 'warning' : 'success'),
                ];
            } catch (\Exception $e) {
                \Log::error('Rector stats error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return [
                    Stat::make('Active Hostels', '0')
                        ->description('Under your oversight')
                        ->descriptionIcon('heroicon-m-building-office')
                        ->color('primary'),
                    Stat::make('Resident Students', '0')
                        ->description('Across all assigned hostels')
                        ->descriptionIcon('heroicon-m-users')
                        ->color('success'),
                    Stat::make('Pending Requests', '0')
                        ->description('Awaiting your decision')
                        ->descriptionIcon('heroicon-m-clock')
                        ->color('success'),
                ];
            }
        });
    }
}

