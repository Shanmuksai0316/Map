<?php

namespace App\Filament\Rector\Widgets\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class OccupancyOverviewWidget extends ChartWidget
{
    protected static ?string $heading = 'Hostel Occupancy (Percent)';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 5;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $service = app(DashboardDataService::class);
        $tenantId = Auth::user()?->tenant_id;

        $data = $service->occupancyByHostel($tenantId);
        $labels = $data['labels'] ?? [];
        $occupied = $data['occupied'] ?? [];
        $available = $data['available'] ?? [];

        $labelsWithPercentage = collect($labels)
            ->map(function (string $label, int $index) use ($occupied, $available): string {
                $occupiedCount = (int) ($occupied[$index] ?? 0);
                $availableCount = (int) ($available[$index] ?? 0);
                $total = $occupiedCount + $availableCount;
                $percentage = $total > 0 ? round(($occupiedCount / $total) * 100, 1) : 0;

                return "{$label} ({$percentage}%)";
            })
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Occupied',
                    'data' => $occupied,
                    'backgroundColor' => ['#059669', '#0284C7', '#D97706', '#DC2626', '#7C3AED'],
                ],
            ],
            'labels' => $labelsWithPercentage,
        ];
    }
}
