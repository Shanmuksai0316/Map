<?php

namespace App\Filament\Widgets\SuperAdmin;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin Dashboard Widget
 * 
 * Shows aggregated metrics across ALL tenants
 * Does NOT require tenant_id
 */
class SuperAdminStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';
    
    public static function canView(): bool
    {
        // Only show for Super Admin role
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }
    
    protected function getStats(): array
    {
        $cacheKey = 'super_admin_stats';
        
        return Cache::remember($cacheKey, 300, function () {
            try {
                $tenants = \App\Models\Tenant::where('status', 'active')->count();
            } catch (\Exception $e) {
                \Log::warning('SuperAdminStatsWidget: Error counting tenants', ['error' => $e->getMessage()]);
                $tenants = 0;
            }
            
            try {
                // Staff are in central database
                $staff = DB::table('users')->where('kind', '!=', 'student')->count();
            } catch (\Exception $e) {
                \Log::warning('SuperAdminStatsWidget: Error counting staff', ['error' => $e->getMessage()]);
                $staff = 0;
            }
            
            // Aggregate across all tenant databases
            $campuses = 0;
            $hostels = 0;
            $students = 0;
            $totalBeds = 0;
            $occupiedBeds = 0;
            $outpassesToday = 0;
            $activeDevices = 0;
            $totalDevices = 0;
            
            try {
                \Stancl\Tenancy\Facades\Tenancy::runForMultiple(
                    \App\Models\Tenant::where('status', 'active')->get(),
                    function ($tenant) use (&$campuses, &$hostels, &$students, &$totalBeds, &$occupiedBeds, &$outpassesToday, &$activeDevices, &$totalDevices) {
                        try {
                            // Aggregate tenant data
                            $campuses += \App\Models\Campus::count();
                            $hostels += \App\Models\Hostel::count();
                            $students += \App\Models\Student::count();
                            
                            // Beds
                            $totalBeds += \App\Models\RoomBed::count();
                            $occupiedBeds += \App\Models\RoomAllocation::where('is_active', true)->count();
                            
                            // OutPasses today
                            $today = now()->startOfDay();
                            $tomorrow = now()->addDay()->startOfDay();
                            $outpassesToday += \App\Models\Domain\OutPass\OutPass::whereBetween('created_at', [$today, $tomorrow])->count();
                            
                            // Devices (if addon enabled)
                            if (class_exists(\App\Domain\Gate\Models\GateDevice::class)) {
                                $totalDevices += \App\Domain\Gate\Models\GateDevice::count();
                                $tenMinutesAgo = now()->subMinutes(10);
                                $activeDevices += \App\Domain\Gate\Models\GateDevice::where('is_active', true)
                                    ->where('last_seen_at', '>=', $tenMinutesAgo)
                                    ->count();
                            }
                        } catch (\Exception $e) {
                            \Log::warning('SuperAdminStatsWidget: Error aggregating tenant data', [
                                'tenant' => $tenant->id,
                                'error' => $e->getMessage()
                            ]);
                            // Continue with next tenant
                        }
                    }
                );
            } catch (\Exception $e) {
                \Log::error('SuperAdminStatsWidget: Error running tenant aggregation', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue with 0s - don't crash the widget
            }
            
            $utilization = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;
            
            // Calculate staff checklist percentage
            $checklistPercent = 0;
            try {
                $totalChecklists = DB::table('checklist_instances')->count();
                $completedChecklists = DB::table('checklist_instances')->whereNotNull('completed_at')->count();
                $checklistPercent = $totalChecklists > 0 ? round(($completedChecklists / $totalChecklists) * 100, 1) : 0;
            } catch (\Exception $e) {
                // Ignore if table doesn't exist
            }

            // Count open requests/tickets
            $openRequests = 0;
            try {
                $openRequests = DB::table('tickets')->whereIn('status', ['open', 'in_progress'])->count();
            } catch (\Exception $e) {
                // Ignore if table doesn't exist
            }

            try {
                return [
                    // 1. Total Tenants
                    Stat::make('Total Tenants', $tenants)
                        ->description('Active organizations')
                        ->icon('heroicon-o-building-office')
                        ->color('primary'),
                    
                    // 2. Total Hostels
                    Stat::make('Total Hostels', $hostels)
                        ->description('Managed facilities')
                        ->icon('heroicon-o-home-modern')
                        ->color('info'),
                    
                    // 3. Total Students
                    Stat::make('Total Students', number_format($students))
                        ->description('Enrolled students')
                        ->icon('heroicon-o-academic-cap')
                        ->color('warning'),
                    
                    // 4. Staff Members
                    Stat::make('Staff Members', $staff)
                        ->description('Across all tenants')
                        ->icon('heroicon-o-users')
                        ->color('success'),
                    
                    // 5. Beds Utilization
                    Stat::make('Bed Utilization', $utilization . '%')
                        ->description($occupiedBeds . ' of ' . $totalBeds . ' beds')
                        ->icon('heroicon-o-chart-bar')
                        ->color($utilization > 90 ? 'danger' : ($utilization > 75 ? 'warning' : 'success')),
                    
                    // 6. Staff Checklist %
                    Stat::make('Staff Checklist', $checklistPercent . '%')
                        ->description('Completion rate')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color($checklistPercent >= 90 ? 'success' : ($checklistPercent >= 70 ? 'warning' : 'danger')),
                    
                    // 7. Open Requests
                    Stat::make('Requests', $openRequests)
                        ->description('Open & in-progress')
                        ->icon('heroicon-o-ticket')
                        ->color($openRequests > 50 ? 'danger' : ($openRequests > 20 ? 'warning' : 'success')),
                ];
            } catch (\Throwable $e) {
                \Log::error('SuperAdminStatsWidget: Error creating stats', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return [];
            }
        });
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
