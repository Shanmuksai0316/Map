<?php

namespace App\Filament\CollegeMgmt\Widgets;

use App\Enums\OutPassStatus;
use App\Filament\CollegeMgmt\Pages\ReportCenter;
use App\Filament\CollegeMgmt\Resources\OutPassResource;
use App\Filament\CollegeMgmt\Resources\StudentResource;
use App\Filament\CollegeMgmt\Resources\TicketResource;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Domain\OutPass\OutPass;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Ticket;
use App\Models\Incident;
use App\Services\FeatureFlagsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CollegeMgmtStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        try {
            $cacheKey = 'college_mgmt_stats_' . auth()->id();
            
            return Cache::remember($cacheKey, 300, function () {
            try {
                // Get tenant ID - prioritize tenant context from subdomain, then user's tenant_id
                $tenantId = null;
                try {
                    if (function_exists('tenant') && tenant()) {
                        $tenantId = tenant()->id;
                    }
                } catch (\Exception $e) {
                    // tenant() might not be available
                }
                
                if (!$tenantId) {
                    $user = auth()->user();
                    $tenantId = $user?->tenant_id;
                }
                
                if (!$tenantId) {
                    \Log::warning('CollegeMgmtStatsOverview: No tenant ID found', [
                        'user_id' => auth()->id(),
                    ]);
                }
                
                // 1. Occupancy % (students / total beds) - PRD §6
                $bedQuery = RoomBed::query();
                if ($tenantId) {
                    $bedQuery->where('tenant_id', $tenantId);
                }
                $totalBeds = $bedQuery->count();
                
                $allocationQuery = RoomAllocation::query()->where('is_active', true);
                if ($tenantId) {
                    $allocationQuery->where('tenant_id', $tenantId);
                }
                $occupiedBeds = $allocationQuery->count();
                $occupancyPercent = $totalBeds > 0 
                    ? round(($occupiedBeds / $totalBeds) * 100, 1) 
                    : 0;
                
                // 2. Approvals Median (7d) - time from request to approval - PRD §6
                $approvalsQuery = OutPass::where('status', OutPassStatus::APPROVED)
                    ->where('decided_at', '>=', now()->subDays(7));
                if ($tenantId) {
                    $approvalsQuery->where('tenant_id', $tenantId);
                }
                $approvalsLast7d = $approvalsQuery
                    ->selectRaw('EXTRACT(EPOCH FROM (decided_at - created_at)) as approval_time')
                    ->get()
                    ->pluck('approval_time')
                    ->filter()
                    ->values();
                
                $medianApprovalTimeHours = $approvalsLast7d->isNotEmpty() 
                    ? round($approvalsLast7d->median() / 3600, 1) 
                    : 0;
                
                // 3. Late Returns (overdue out-passes) - PRD §6
                $lateReturnsQuery = OutPass::where('status', OutPassStatus::APPROVED)
                    ->where('expected_return', '<', now())
                    ->whereNull('actual_in_time');
                if ($tenantId) {
                    $lateReturnsQuery->where('tenant_id', $tenantId);
                }
                $lateReturns = $lateReturnsQuery->count();
                
                // 4. Ticket Aging (median days to resolution) - PRD §6
                $ticketsQuery = Ticket::where('status', 'resolved')
                    ->where('updated_at', '>=', now()->subDays(30));
                if ($tenantId) {
                    $ticketsQuery->where('tenant_id', $tenantId);
                }
                $resolvedTickets = $ticketsQuery
                    ->selectRaw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as age_days')
                    ->get()
                    ->pluck('age_days')
                    ->filter()
                    ->values();
                
                $medianTicketAge = $resolvedTickets->isNotEmpty() 
                    ? round($resolvedTickets->median(), 1) 
                    : 0;
                
                $studentQuery = Student::query();
                if ($tenantId) {
                    $studentQuery->where('tenant_id', $tenantId);
                }
                $totalStudents = $studentQuery->count();

                $maleStudentsQuery = Student::query()
                    ->whereRaw("LOWER(COALESCE(gender, '')) IN ('male','m','boy','boys')");
                if ($tenantId) {
                    $maleStudentsQuery->where('tenant_id', $tenantId);
                }
                $maleStudents = $maleStudentsQuery->count();

                $femaleStudentsQuery = Student::query()
                    ->whereRaw("LOWER(COALESCE(gender, '')) IN ('female','f','girl','girls')");
                if ($tenantId) {
                    $femaleStudentsQuery->where('tenant_id', $tenantId);
                }
                $femaleStudents = $femaleStudentsQuery->count();

                $knownGenderTotal = $maleStudents + $femaleStudents;
                $malePercent = $knownGenderTotal > 0 ? round(($maleStudents / $knownGenderTotal) * 100, 1) : 0;
                $femalePercent = $knownGenderTotal > 0 ? round(($femaleStudents / $knownGenderTotal) * 100, 1) : 0;
                
                // 6. Checklist Compliance (% completed today) - PRD §6
                $checklistsToday = 0;
                $checklistsCompletedToday = 0;
                $compliancePercent = 0;
                if (class_exists(\App\Domain\Checklists\Models\ChecklistInstance::class)) {
                    $checklistsTodayQuery = \App\Domain\Checklists\Models\ChecklistInstance::whereDate('created_at', today());
                    if ($tenantId) {
                        $checklistsTodayQuery->where('tenant_id', $tenantId);
                    }
                    $checklistsToday = $checklistsTodayQuery->count();
                    
                    $checklistsCompletedQuery = \App\Domain\Checklists\Models\ChecklistInstance::whereDate('created_at', today())
                        ->where('status', 'completed');
                    if ($tenantId) {
                        $checklistsCompletedQuery->where('tenant_id', $tenantId);
                    }
                    $checklistsCompletedToday = $checklistsCompletedQuery->count();
                    $compliancePercent = $checklistsToday > 0 
                        ? round(($checklistsCompletedToday / $checklistsToday) * 100, 1) 
                        : 0;
                }
                
                // 7. Sports Utilisation (if addon enabled) - PRD §6
                $sportsUtilisation = 0;
                if (class_exists(FeatureFlagsService::class) && app(FeatureFlagsService::class)->enabled('addon_sports')) {
                    if (class_exists(\App\Models\Booking::class)) {
                        $bookingsQuery = \App\Models\Booking::where('created_at', '>=', now()->subDays(30));
                        if ($tenantId) {
                            $bookingsQuery->where('tenant_id', $tenantId);
                        }
                        $totalBookings = $bookingsQuery->count();
                        $totalSlots = 30 * 4; // Example: 30 days * 4 slots/day
                        $sportsUtilisation = $totalSlots > 0 
                            ? round(($totalBookings / $totalSlots) * 100, 1) 
                            : 0;
                    }
                }
                
                // 8. Total Hostels
                $hostelQuery = Hostel::query();
                if ($tenantId) {
                    $hostelQuery->where('tenant_id', $tenantId);
                }
                $totalHostels = $hostelQuery->count();

                $ticketsBase = Ticket::query();
                if ($tenantId) {
                    $ticketsBase->where('tenant_id', $tenantId);
                }
                $requestsTotal = (clone $ticketsBase)->count();
                $requestsPending = (clone $ticketsBase)->whereIn('status', ['open', 'on_hold'])->count();
                $requestsInProgress = (clone $ticketsBase)->where('status', 'in_progress')->count();
                $requestsCompleted = (clone $ticketsBase)->whereIn('status', ['resolved', 'closed'])->count();
                
                // Log counts for debugging
                \Log::info('CollegeMgmtStatsOverview: KPI counts', [
                    'tenant_id' => $tenantId,
                    'total_students' => $totalStudents,
                    'total_hostels' => $totalHostels,
                    'total_beds' => $totalBeds,
                    'occupied_beds' => $occupiedBeds,
                ]);

                // Build stats array with PRD §6 KPIs
                $stats = [
                    Stat::make('Occupancy', $occupancyPercent . '%')
                        ->description("{$occupiedBeds}/{$totalBeds} beds | Boys: {$maleStudents} ({$malePercent}%) | Girls: {$femaleStudents} ({$femalePercent}%)")
                        ->icon('heroicon-m-home')
                        ->color($occupancyPercent > 90 ? 'danger' : ($occupancyPercent > 75 ? 'warning' : 'success'))
                        ->url(StudentResource::getUrl('index')),
                    
                    Stat::make('Approval Median', $medianApprovalTimeHours . 'h')
                        ->description('Last 7 days')
                        ->icon('heroicon-m-clock')
                        ->color($medianApprovalTimeHours > 24 ? 'danger' : ($medianApprovalTimeHours > 12 ? 'warning' : 'success')),
                    
                    Stat::make('Late Returns', number_format($lateReturns))
                        ->description('Overdue')
                        ->icon('heroicon-m-exclamation-triangle')
                        ->color($lateReturns > 5 ? 'danger' : ($lateReturns > 0 ? 'warning' : 'success'))
                        ->url(OutPassResource::getUrl('index')),
                    
                    Stat::make('Ticket Aging', $medianTicketAge . 'd')
                        ->description('Median resolution time')
                        ->icon('heroicon-m-wrench-screwdriver')
                        ->color($medianTicketAge > 7 ? 'danger' : ($medianTicketAge > 3 ? 'warning' : 'success'))
                        ->url(TicketResource::getUrl('index')),
                    
                    Stat::make('Checklist Compliance', $compliancePercent . '%')
                        ->description('Today')
                        ->icon('heroicon-m-check-circle')
                        ->color($compliancePercent < 80 ? 'danger' : ($compliancePercent < 95 ? 'warning' : 'success'))
                        ->url(ReportCenter::getUrl()),
                ];
                
                // Add Sports Utilisation only if addon enabled
                if ($sportsUtilisation > 0 || (class_exists(FeatureFlagsService::class) && app(FeatureFlagsService::class)->enabled('addon_sports'))) {
                    $stats[] = Stat::make('Sports Utilisation', $sportsUtilisation . '%')
                        ->description('Last 30 days')
                        ->icon('heroicon-m-trophy')
                        ->color($sportsUtilisation < 20 ? 'warning' : 'success');
                }
                
                // Add basic counts for reference
                $stats[] = Stat::make('Total Students', number_format($totalStudents))
                    ->description('Current occupancy')
                    ->icon('heroicon-m-users')
                    ->color('primary')
                    ->url(StudentResource::getUrl('index'));
                
                $stats[] = Stat::make('Total Hostels', number_format($totalHostels))
                    ->description('Managed hostels')
                    ->icon('heroicon-m-building-office')
                    ->color('primary');

                $stats[] = Stat::make('Requests Total', number_format($requestsTotal))
                    ->description('All tickets')
                    ->icon('heroicon-m-inbox-stack')
                    ->color('primary')
                    ->url(TicketResource::getUrl('index'));

                $stats[] = Stat::make('Pending', number_format($requestsPending))
                    ->description('Open + On Hold')
                    ->icon('heroicon-m-clock')
                    ->color($requestsPending > 0 ? 'warning' : 'success')
                    ->url(TicketResource::getUrl('index'));

                $stats[] = Stat::make('In Progress', number_format($requestsInProgress))
                    ->description('Work in progress')
                    ->icon('heroicon-m-arrow-path')
                    ->color('info')
                    ->url(TicketResource::getUrl('index'));

                $stats[] = Stat::make('Completed', number_format($requestsCompleted))
                    ->description('Resolved + Closed')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->url(TicketResource::getUrl('index'));
                
                return $stats;
            } catch (\Exception $e) {
                // Log the error for debugging
                \Log::error('CollegeMgmtStatsOverview: Error in getStats()', [
                    'error' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 1000),
                    'user_id' => auth()->id(),
                ]);
                
                // Return safe defaults if any error occurs
                return [
                    Stat::make('Total Students', '0')
                        ->description('Current occupancy')
                        ->icon('heroicon-m-users')
                        ->color('gray'),
                    
                    Stat::make('Total Hostels', '0')
                        ->description('Managed hostels')
                        ->icon('heroicon-m-building-office')
                        ->color('gray'),
                    
                    Stat::make('System Status', 'Error')
                        ->description('Please refresh or contact support')
                        ->icon('heroicon-m-exclamation-triangle')
                        ->color('warning'),
                ];
            }
            });
        } catch (\Throwable $e) {
            // Top-level error handling - ensure widget always returns something
            \Log::error('CollegeMgmtStatsOverview: Top-level error in getStats()', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
                'user_id' => auth()->id(),
            ]);
            
            return [
                Stat::make('System Error', 'Error')
                    ->description('Please refresh or contact support')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }
}
