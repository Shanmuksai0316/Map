<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SuperAdminKpisWidget extends BaseWidget
{
    protected function getStats(): array
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user->hasRole('Super Admin');
            
            // Super Admin sees cross-tenant data, others see single tenant
            $tenantId = $isSuperAdmin ? null : $user->tenant_id;
            
            $repo = app(KpisRepository::class);
            
            // Cache KPIs for 5 minutes with tenant context
            $cacheKey = 'dash:super:' . ($tenantId ?? 'all');

            // Temporarily disable caching to avoid Redis issues - compute directly
            try {
                // Fetch all data once to avoid multiple calls
                $totalHostels = $this->safeCall(fn() => $repo->totalHostels($tenantId), 0);
                $bedsUtilization = $this->safeCall(fn() => $repo->bedsUtilizationPercent($tenantId), 0.0);
                $availableBeds = $this->safeCall(fn() => $repo->availableBeds($tenantId), 0);
                $outPasses = $this->safeCall(fn() => $repo->outPassesToday($tenantId), ['total' => 0, 'approved' => 0, 'pending' => 0]);
                $lateReturns = $this->safeCall(fn() => $repo->lateReturnsToday($tenantId), 0);
                $tickets = $this->safeCall(fn() => $repo->ticketsOpenByPriority($tenantId), ['P1' => 0, 'P2' => 0, 'P3' => 0, 'total' => 0]);
                $slaBreached = $this->safeCall(fn() => $repo->ticketsSlaBreachedOpen($tenantId), 0);
                $attendanceClosure = $this->safeCall(fn() => $repo->attendanceClosure7dPercent($tenantId), 0.0);
                $checklistOnTime = $this->safeCall(fn() => $repo->checklistOnTime7dPercent($tenantId), 0.0);
                $deviceHealth = $this->safeCall(fn() => $repo->deviceHealth($tenantId), ['active' => 0, 'total' => 0, 'stale' => 0]);

                return [
                    // 1. Total Hostels
                    Stat::make('Total Hostels', $totalHostels)
                        ->description('Across tenant')
                        ->icon('heroicon-o-building-office-2')
                        ->color('primary')
                        ->url(url('/admin/hostels'))
                        ->openUrlInNewTab(),

                    // 2. Beds Utilization
                    Stat::make('Beds Utilization', $bedsUtilization . '%')
                        ->description('Occupancy rate')
                        ->icon('heroicon-o-chart-bar')
                        ->color($bedsUtilization > 90 ? 'danger' : 'success'),

                    // 3. Available Beds
                    Stat::make('Available Beds', $availableBeds)
                        ->description('Ready for allocation')
                        ->icon('heroicon-o-check-circle')
                        ->color('warning'),

                    // 4. OutPasses Today
                    Stat::make('OutPasses Today', $outPasses['total'])
                        ->description($outPasses['approved'] . ' approved, ' . $outPasses['pending'] . ' pending')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('info'),

                    // 5. Late Returns Today
                    Stat::make('Late Returns Today', $lateReturns)
                        ->description('Overdue returns')
                        ->icon('heroicon-o-clock')
                        ->color('danger'),

                    // 6. Open Tickets by Priority
                    Stat::make('Open Tickets', $tickets['total'])
                        ->description('P1: ' . $tickets['P1'] . ', P2: ' . $tickets['P2'] . ', P3: ' . $tickets['P3'])
                        ->icon('heroicon-o-ticket')
                        ->color('warning'),

                    // 7. SLA Breached Tickets
                    Stat::make('SLA Breached', $slaBreached)
                        ->description('Overdue tickets')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger'),

                    // 8. Attendance Closure (7d)
                    Stat::make('Attendance Closure', $attendanceClosure . '%')
                        ->description('Last 7 days')
                        ->icon('heroicon-o-check-badge')
                        ->color($attendanceClosure > 80 ? 'success' : 'warning'),

                    // 9. Checklist On-Time (7d)
                    Stat::make('Checklist On-Time', $checklistOnTime . '%')
                        ->description('Last 7 days')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color($checklistOnTime > 80 ? 'success' : 'warning'),

                    // 10. Device Health
                    Stat::make('Device Health', $deviceHealth['active'] . '/' . $deviceHealth['total'])
                        ->description($deviceHealth['stale'] . ' stale devices')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->color($deviceHealth['stale'] > 0 ? 'warning' : 'success'),
                ];
            } catch (\Throwable $e) {
                \Log::error('SuperAdminKpisWidget: Error fetching stats', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Return empty stats instead of crashing
                return [];
            }
        } catch (\Throwable $e) {
            \Log::error('SuperAdminKpisWidget: Error in getStats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Safely call a repository method and return default value on error
     */
    private function safeCall(callable $callback, $default)
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            \Log::warning('SuperAdminKpisWidget: Error in repository call', [
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }
}
