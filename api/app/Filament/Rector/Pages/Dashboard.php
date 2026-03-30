<?php

namespace App\Filament\Rector\Pages;

use App\Filament\Rector\Widgets\Charts\ApprovalTrendWidget;
use App\Filament\Rector\Widgets\Charts\OccupancyOverviewWidget;
use App\Filament\Rector\Widgets\PendingApprovalsWidget;
use App\Filament\Rector\Widgets\RecentDecisionsWidget;
use App\Filament\Rector\Widgets\RectorGreeting;
use App\Filament\Rector\Widgets\RectorStatsOverview;
use App\Filament\Rector\Widgets\SLAPerformanceWidget;
use App\Services\Reports\RectorReportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Rector Dashboard';

    public function getWidgets(): array
    {
        try {
            return [
                // Greeting header
                RectorGreeting::class,
                // Core stats overview (pending approvals, hostels, students)
                RectorStatsOverview::class,
                // Chart.js visualizations
                ApprovalTrendWidget::class,          // Line — approval trend
                OccupancyOverviewWidget::class,      // Donut — occupancy
                // Table of urgent pending approvals
                PendingApprovalsWidget::class,
                // SLA performance chart
                SLAPerformanceWidget::class,
                // Recent decisions by this Rector
                RecentDecisionsWidget::class,
            ];
        } catch (\Throwable $e) {
            if (app()->environment('local')) {
                \Log::error('RECTOR_DASHBOARD_ERR', [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
            throw $e;
        }
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_report')
                ->label('Download Monthly Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('month')
                        ->label('Month')
                        ->options([
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ])
                        ->default(now()->month)
                        ->required(),
                    Forms\Components\Select::make('year')
                        ->label('Year')
                        ->options(array_combine(range(now()->year, now()->year - 2), range(now()->year, now()->year - 2)))
                        ->default(now()->year)
                        ->required(),
                    Forms\Components\Select::make('format')
                        ->label('Format')
                        ->options([
                            'pdf' => 'PDF Report',
                            'csv' => 'CSV Data',
                        ])
                        ->default('pdf')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $tenantId = auth()->user()->tenant_id;
                    $reportService = app(RectorReportService::class);

                    try {
                        if ($data['format'] === 'pdf') {
                            $url = $reportService->generateMonthlyPDF($tenantId, $data['month'], $data['year']);
                        } else {
                            $url = $reportService->generateMonthlyCSV($tenantId, $data['month'], $data['year']);
                        }

                        Notification::make()
                            ->title('Report Generated')
                            ->body('Your monthly report has been generated and is ready for download.')
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('download')
                                    ->label('Download')
                                    ->url($url)
                                    ->openUrlInNewTab(),
                            ])
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Report Generation Failed')
                            ->body('Failed to generate the report. Please try again.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

