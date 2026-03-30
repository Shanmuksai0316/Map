<?php

namespace App\Filament\CampusManager\Widgets\CampusManager;

use App\Models\Hostel;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        try {
            // Get tenant ID - prioritize tenant context from subdomain, then user's tenant_id
            $tenantId = null;
            try {
                // First try to get from tenant context (from subdomain)
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }
            } catch (\Exception $e) {
                // tenant() might not be available
                \Log::warning('StatsOverview: tenant() call failed', ['error' => $e->getMessage()]);
            }
            
            // Fallback to user's tenant_id if tenant context not available
            if (!$tenantId) {
                $user = auth()->user();
                $tenantId = $user?->tenant_id;
            }
            
            // Log tenant ID for debugging
            if (!$tenantId) {
                \Log::warning('StatsOverview: No tenant ID found', [
                    'user_id' => auth()->id(),
                    'user_tenant_id' => auth()->user()?->tenant_id,
                ]);
            }
            
            // For Campus Manager panel accessed via tenant subdomain, we MUST use that tenant's data
            // Even if user is Super Admin, when accessing via ppcu.mapservices.in, show ppcu's data
            $hostelQuery = Hostel::query();
            $studentQuery = Student::query();
            
            // Explicitly scope by tenant_id if we have it (bypass TenantScope's Super Admin bypass)
            if ($tenantId) {
                $hostelQuery->where('tenant_id', $tenantId);
                $studentQuery->where('tenant_id', $tenantId);
            }
            
            $hostelCount = $hostelQuery->count();
            $studentCount = $studentQuery->count();
            
            // Log counts for debugging
            \Log::info('StatsOverview: KPI counts', [
                'tenant_id' => $tenantId,
                'hostel_count' => $hostelCount,
                'student_count' => $studentCount,
            ]);

            return [
                Stat::make('Active Hostels', number_format($hostelCount))
                    ->description('Managed under your campuses')
                    ->descriptionIcon('heroicon-m-building-office')
                    ->color('primary'),

                Stat::make('Resident Students', number_format($studentCount))
                    ->description('Across your hostels')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('success'),
            ];
        } catch (\Exception $e) {
            \Log::error('StatsOverview widget error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => auth()->user()?->tenant_id,
                'tenant_context' => function_exists('tenant') ? (tenant() ? tenant()->id : null) : null,
            ]);
            // If there's any database error, return safe defaults
            return [
                Stat::make('Active Hostels', '0')
                    ->description('Managed under your campuses')
                    ->descriptionIcon('heroicon-m-building-office')
                    ->color('primary'),
                Stat::make('Resident Students', '0')
                    ->description('Across your hostels')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('success'),
            ];
        }
    }
}
