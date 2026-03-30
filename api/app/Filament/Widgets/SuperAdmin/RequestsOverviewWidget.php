<?php

namespace App\Filament\Widgets\SuperAdmin;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RequestsOverviewWidget extends BaseWidget
{
    protected ?string $heading = 'Request KPIs';

    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('super_admin_requests_stats', 300, function () {
            $pendingRequests = 0;
            $inProgressRequests = 0;
            $completedToday = 0;
            $overdueRequests = 0;

            try {
                // Get ticket counts from central database
                $pendingRequests = DB::table('tickets')
                    ->where('status', 'open')
                    ->count();

                $inProgressRequests = DB::table('tickets')
                    ->where('status', 'in_progress')
                    ->count();

                $completedToday = DB::table('tickets')
                    ->where('status', 'resolved')
                    ->whereDate('updated_at', today())
                    ->count();

                // Overdue = open/in_progress and created more than 48 hours ago
                $overdueRequests = DB::table('tickets')
                    ->whereIn('status', ['open', 'in_progress'])
                    ->where('created_at', '<', now()->subHours(48))
                    ->count();
            } catch (\Exception $e) {
                \Log::warning('RequestsOverviewWidget: Error loading stats', ['error' => $e->getMessage()]);
            }

            return [
                'pending' => $pendingRequests,
                'in_progress' => $inProgressRequests,
                'completed_today' => $completedToday,
                'overdue' => $overdueRequests,
            ];
        });

        return [
            Stat::make('Pending Requests', $stats['pending'])
                ->description('Awaiting assignment')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('In Progress', $stats['in_progress'])
                ->description('Currently being worked on')
                ->icon('heroicon-o-arrow-path')
                ->color('info'),

            Stat::make('Completed Today', $stats['completed_today'])
                ->description('Resolved today')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Overdue', $stats['overdue'])
                ->description('Pending > 48 hours')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}
