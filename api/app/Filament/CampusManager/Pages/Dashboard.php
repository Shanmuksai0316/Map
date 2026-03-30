<?php

namespace App\Filament\CampusManager\Pages;

use App\Filament\CampusManager\Widgets\CampusManager\StatsOverview;
use App\Filament\CampusManager\Widgets\CampusManager\ChecklistComplianceWidget;
use App\Filament\CampusManager\Widgets\CampusManager\RoomChangeQueueWidget;
use App\Filament\CampusManager\Widgets\CampusManager\CheckoutSummaryWidget;
use App\Filament\CampusManager\Widgets\CampusManager\OccupancyMetricsWidget;
use App\Filament\CampusManager\Widgets\CampusManager\ReminderWidget;
use App\Filament\CampusManager\Widgets\Charts\AttendanceTrendWidget;
use App\Filament\CampusManager\Widgets\Charts\CheckoutTimelineWidget;
use App\Filament\CampusManager\Widgets\Charts\OccupancyChartWidget;
use App\Filament\CampusManager\Widgets\Charts\PassRequestTrendWidget;
use App\Filament\CampusManager\Widgets\Charts\RequestStatusWidget;
use App\Filament\CampusManager\Widgets\DashboardTimeFilter;
use App\Filament\CampusManager\Widgets\HostelSwitcher;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    // We use the panel brand name as the main title in the header,
    // so keep the page title empty to avoid a duplicate heading.
    protected static ?string $title = null;

    public function getWidgets(): array
    {
        try {
            return [
                // Hostel switcher at the very top
                HostelSwitcher::class,
                // Global time range filter
                DashboardTimeFilter::class,
                // Core stats overview (scorecards)
                StatsOverview::class,
                // Chart.js visualizations
                OccupancyChartWidget::class,       // Donut — occupancy by hostel
                RequestStatusWidget::class,        // Bar — request status breakdown
                AttendanceTrendWidget::class,       // Line — attendance trend
                CheckoutTimelineWidget::class,     // Bar — upcoming checkouts
                PassRequestTrendWidget::class,     // Line — pass request trend
                // Legacy widgets below charts
                ChecklistComplianceWidget::class,
                RoomChangeQueueWidget::class,
            ];
        } catch (\Throwable $e) {
            // Log errors in ALL environments for debugging
            \Log::error('CM_DASHBOARD_WIDGETS_ERR', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return minimal widgets to prevent complete failure
            return [
                StatsOverview::class,
            ];
        }
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getHeading(): string
    {
        try {
            $user = auth()->user();
            
            // Get tenant ID from subdomain context
            $tenantId = null;
            try {
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }
            } catch (\Exception $e) {
                // tenant() might not be available
            }
            
            if (!$tenantId && $user) {
                $tenantId = $user->tenant_id;
            }
            
            // Get user name - prefer actual name, fallback to finding Campus Manager for tenant
            $userName = 'there';
            if ($user) {
                $name = trim($user->name ?? '');
                
                // If user has a name and it's not "Super Admin" or empty, use it
                if ($name && strtolower($name) !== 'super admin' && strtolower($name) !== 'superadmin') {
                    $userName = $name;
                } elseif ($user->hasRole('Campus Manager')) {
                    // If user is Campus Manager, use their name, employee_id, or email
                    $userName = $name ?: ($user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'Campus Manager');
                } elseif ($name && (strtolower($name) === 'super admin' || strtolower($name) === 'superadmin')) {
                    // For Super Admin accessing tenant panel, find the actual Campus Manager for this tenant
                    if ($tenantId) {
                        try {
                            $campusManager = \App\Models\User::where('tenant_id', $tenantId)
                                ->whereHas('roles', function ($q) {
                                    $q->where('name', 'Campus Manager');
                                })
                                ->first();
                            
                            if ($campusManager && $campusManager->name && strtolower($campusManager->name) !== 'super admin') {
                                $userName = $campusManager->name;
                            } elseif ($campusManager) {
                                $userName = $campusManager->employee_id ?? ($campusManager->email ? explode('@', $campusManager->email)[0] : null) ?? 'Campus Manager';
                            } else {
                                // No Campus Manager found, use tenant name + "Campus Manager"
                                try {
                                    if (function_exists('tenant') && tenant()) {
                                        $tenant = tenant();
                                        $tenantName = $tenant->name ?? '';
                                        $userName = $tenantName ? ($tenantName . ' Campus Manager') : 'Campus Manager';
                                    } else {
                                        $userName = 'Campus Manager';
                                    }
                                } catch (\Exception $e) {
                                    $userName = 'Campus Manager';
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::warning('CM_DASHBOARD: Failed to find Campus Manager', [
                                'tenant_id' => $tenantId,
                                'error' => $e->getMessage(),
                            ]);
                            // Fallback to tenant name + "Campus Manager"
                            try {
                                if (function_exists('tenant') && tenant()) {
                                    $tenant = tenant();
                                    $tenantName = $tenant->name ?? '';
                                    $userName = $tenantName ? ($tenantName . ' Campus Manager') : 'Campus Manager';
                                } else {
                                    $userName = 'Campus Manager';
                                }
                            } catch (\Exception $e2) {
                                $userName = 'Campus Manager';
                            }
                        }
                    } else {
                        $userName = $user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'Campus Manager';
                    }
                } else {
                    // No name at all, try employee_id or email, or tenant name + "Campus Manager"
                    if ($tenantId) {
                        try {
                            if (function_exists('tenant') && tenant()) {
                                $tenant = tenant();
                                $tenantName = $tenant->name ?? '';
                                $userName = $tenantName ? ($tenantName . ' Campus Manager') : ($user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'Campus Manager');
                            } else {
                                $userName = $user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'Campus Manager';
                            }
                        } catch (\Exception $e) {
                            $userName = $user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'Campus Manager';
                        }
                    } else {
                        $userName = $user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'Campus Manager';
                    }
                }
            } elseif ($tenantId) {
                // No user authenticated, but we have tenant context - use tenant name
                try {
                    if (function_exists('tenant') && tenant()) {
                        $tenant = tenant();
                        $tenantName = $tenant->name ?? '';
                        $userName = $tenantName ? ($tenantName . ' Campus Manager') : 'Campus Manager';
                    } else {
                        $userName = 'Campus Manager';
                    }
                } catch (\Exception $e) {
                    $userName = 'Campus Manager';
                }
            }

            // Use campus local time (IST) for greeting.
            $hour = now('Asia/Kolkata')->hour;

            if ($hour < 12) {
                $greeting = 'Good morning';
            } elseif ($hour < 17) {
                $greeting = 'Good afternoon';
            } else {
                $greeting = 'Good evening';
            }

            return sprintf('%s, %s', $greeting, $userName);
        } catch (\Throwable $e) {
            \Log::error('CM_DASHBOARD_HEADING_ERR', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
                'user_id' => auth()->id(),
            ]);
            return 'Dashboard';
        }
    }
}
