<?php

namespace App\Filament\CampusManager\Widgets\CampusManager;

use App\Models\Notice;
use App\Models\RoomBed;
use App\Models\Student;
use Filament\Widgets\Widget;

class OccupancyMetricsWidget extends Widget
{
    protected static string $view = 'filament.campus-manager.widgets.occupancy-metrics';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    protected function getViewData(): array
    {
        try {
            // Get tenant ID - prioritize tenant context from subdomain
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
            
            // Explicitly scope by tenant_id to ensure we get data for the current tenant subdomain
            $bedQuery = RoomBed::query();
            if ($tenantId) {
                $bedQuery->where('tenant_id', $tenantId);
            }
            
            $totalBeds = $bedQuery->count();
            $availableBeds = (clone $bedQuery)->where('status', 'available')->count();
            $occupiedBeds = max($totalBeds - $availableBeds, 0);
            $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100) : 0;

            $studentQuery = Student::query();
            if ($tenantId) {
                $studentQuery->where('tenant_id', $tenantId);
            }
            $unassignedStudents = $studentQuery
                ->whereDoesntHave('roomAllocations', function ($query) {
                    $query->where('is_active', true);
                })
                ->count();

            // Notice uses target_tenant_id, not tenant_id, and doesn't use TenantScoped
            // Use the scopeActive() method to get active notices
            // Handle case where target_tenant_id column might not exist
            try {
                $noticeQuery = Notice::query()->active();
                if ($tenantId) {
                    try {
                        $noticeQuery->forTenant($tenantId);
                    } catch (\Exception $e) {
                        // If forTenant fails (column doesn't exist), just get active notices
                        \Log::warning('OccupancyMetricsWidget: forTenant scope failed, using active notices only', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $activeNotices = $noticeQuery->count();
            } catch (\Exception $e) {
                \Log::warning('OccupancyMetricsWidget: Error getting notices', [
                    'error' => $e->getMessage(),
                ]);
                $activeNotices = 0;
            }

            return [
                'totalBeds' => $totalBeds,
                'availableBeds' => $availableBeds,
                'occupancyRate' => $occupancyRate,
                'unassignedStudents' => $unassignedStudents,
                'activeNotices' => $activeNotices,
            ];
        } catch (\Exception $e) {
            \Log::error('OccupancyMetricsWidget error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'totalBeds' => 0,
                'availableBeds' => 0,
                'occupancyRate' => 0,
                'unassignedStudents' => 0,
                'activeNotices' => 0,
            ];
        }
    }
}

