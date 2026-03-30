<?php

namespace App\Filament\CampusManager\Widgets\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class CheckoutTimelineWidget extends ChartWidget
{
    protected static ?string $heading = 'Upcoming Checkouts';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 5;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $service = app(DashboardDataService::class);
        $tenantId = Auth::user()?->tenant_id;
        $hostelId = session('active_hostel_id');

        $data = $service->checkoutTimeline($tenantId, $hostelId);

        return [
            'datasets' => [
                [
                    'label' => 'Students',
                    'data' => $data['counts'] ?? [],
                    'backgroundColor' => ['#DC2626', '#F59E0B', '#3B82F6', '#059669', '#8B5CF6', '#6B7280'],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
            'indexAxis' => 'y',
        ];
    }
}
