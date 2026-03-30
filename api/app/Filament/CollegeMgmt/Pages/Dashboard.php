<?php

namespace App\Filament\CollegeMgmt\Pages;

use App\Filament\CollegeMgmt\Widgets\CollegeMgmtStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'College Management Dashboard';

    // Removed mount() method - access control is handled by panel configuration and middleware
    // If needed, we can add it back, but Filament's BaseDashboard may not have mount()

    public function getHeading(): string
    {
        try {
            $user = auth()->user();
            $tenantId = null;
            try {
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }
            } catch (\Exception $e) {}
            if (!$tenantId && $user) {
                $tenantId = $user->tenant_id;
            }
            $userName = 'there';
            if ($user) {
                $name = trim($user->name ?? '');
                if ($name && strtolower($name) !== 'super admin' && strtolower($name) !== 'superadmin') {
                    $userName = $name;
                } elseif ($user->hasAnyRole(['College Management', 'College Mgmt'])) {
                    $userName = $name ?: ($user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'College Management');
                } elseif ($name && (strtolower($name) === 'super admin' || strtolower($name) === 'superadmin')) {
                    if ($tenantId) {
                        try {
                            $collegeMgmt = \App\Models\User::where('tenant_id', $tenantId)
                                ->whereHas('roles', function ($q) {
                                    $q->whereIn('name', ['College Management', 'College Mgmt']);
                                })
                                ->first();
                            if ($collegeMgmt && $collegeMgmt->name && strtolower($collegeMgmt->name) !== 'super admin') {
                                $userName = $collegeMgmt->name;
                            } elseif ($collegeMgmt) {
                                $userName = $collegeMgmt->employee_id ?? ($collegeMgmt->email ? explode('@', $collegeMgmt->email)[0] : null) ?? 'College Management';
                            } else {
                                try {
                                    if (function_exists('tenant') && tenant()) {
                                        $tenant = tenant();
                                        $tenantName = $tenant->name ?? '';
                                        $userName = $tenantName ? ($tenantName . ' College Management') : 'College Management';
                                    } else {
                                        $userName = 'College Management';
                                    }
                                } catch (\Exception $e) {
                                    $userName = 'College Management';
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::warning('COLLEGE_DASHBOARD: Failed to find College Management user', ['tenant_id' => $tenantId, 'error' => $e->getMessage(),]);
                            try {
                                if (function_exists('tenant') && tenant()) {
                                    $tenant = tenant();
                                    $tenantName = $tenant->name ?? '';
                                    $userName = $tenantName ? ($tenantName . ' College Management') : 'College Management';
                                } else {
                                    $userName = 'College Management';
                                }
                            } catch (\Exception $e2) {
                                $userName = 'College Management';
                            }
                        }
                    } else {
                        $userName = $user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'College Management';
                    }
                } else {
                    $userName = $user->employee_id ?? ($user->email ? explode('@', $user->email)[0] : null) ?? 'College Management';
                }
            } elseif ($tenantId) {
                try {
                    if (function_exists('tenant') && tenant()) {
                        $tenant = tenant();
                        $tenantName = $tenant->name ?? '';
                        $userName = $tenantName ? ($tenantName . ' College Management') : 'College Management';
                    } else {
                        $userName = 'College Management';
                    }
                } catch (\Exception $e) {
                    $userName = 'College Management';
                }
            }
            $hour = now('Asia/Kolkata')->hour;
            $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
            return sprintf('%s, %s', $greeting, $userName);
        } catch (\Throwable $e) {
            \Log::error('COLLEGE_DASHBOARD_HEADING_ERR', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
                'user_id' => auth()->id(),
            ]);
            return 'College Management Dashboard';
        }
    }

    public function getWidgets(): array
    {
        try {
            return [
                CollegeMgmtStatsOverview::class,
            ];
        } catch (\Throwable $e) {
            // Log error in all environments for debugging
            \Log::error('COLLEGE_DASHBOARD_WIDGET_ERR', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
                'user_id' => auth()->id(),
            ]);
            
            // Return minimal widgets if stats widget fails - ensure dashboard still renders
            return [];
        }
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }
}
