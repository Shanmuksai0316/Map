<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\ChartWidget;

class OutPassTrend extends ChartWidget
{
    protected static ?string $heading = 'Out-Pass Approvals (14 Days)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        $hostelIds = \App\Models\Hostel::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $repo = app(KpisRepository::class);
        $data = $repo->outPassDailyCounts($tenantId, 14, $hostelIds);

        return [
            'datasets' => [
                [
                    'label' => 'Approved Out-Passes',
                    'data' => array_values($data),
                    'borderColor' => '#1E56D9',
                    'backgroundColor' => 'rgba(30, 86, 217, 0.1)',
                ],
            ],
            'labels' => array_map(fn($d) => date('M d', strtotime($d)), array_keys($data)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

