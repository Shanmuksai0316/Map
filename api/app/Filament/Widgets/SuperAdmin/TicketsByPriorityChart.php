<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class TicketsByPriorityChart extends ChartWidget
{
    protected static ?string $heading = 'Tickets by Priority';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    protected static ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id ?? 'all';
        
        $repo = app(KpisRepository::class);
        
        $cacheKey = 'dash:super:tickets:' . $tenantId;
        
        return Cache::remember($cacheKey, 300, function () use ($repo, $tenantId) {
            try {
                $data = $repo->ticketsOpenByPriority($tenantId);
            } catch (\Throwable $e) {
                $data = [];
            }
            
            return [
                'datasets' => [
                    [
                        'label' => 'Open Tickets',
                        'data' => [
                            $data['P1'] ?? 0,
                            $data['P2'] ?? 0,
                            $data['P3'] ?? 0,
                        ],
                        'backgroundColor' => ['#ef4444', '#f59e0b', '#10b981'],
                        'borderColor' => ['#dc2626', '#d97706', '#059669'],
                    ],
                ],
                'labels' => ['P1 (Critical)', 'P2 (High)', 'P3 (Normal)'],
            ];
        });
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '58%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
