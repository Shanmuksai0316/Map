<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AttendanceClosureChart extends ChartWidget
{
    protected static ?string $heading = 'Attendance Closure Trend (7 days)';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    /**
     * @var view-string
     */
    protected static string $view = 'filament.widgets.attendance-closure-chart';

    protected static ?string $maxHeight = '400px';

    protected function getData(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id ?? 'all';
        
        $repo = app(KpisRepository::class);
        
        $cacheKey = 'dash:super:attendance:v2:' . $tenantId;
        
        return Cache::remember($cacheKey, 300, function () use ($repo, $tenantId) {
            $data = $repo->attendanceClosure7dTrend($tenantId);
            
            // Handle both flat array and associative array returns
            if (is_array($data) && !empty($data) && array_is_list($data)) {
                $percentages = $data;
                $dates = collect(range(6, 0, -1))->map(fn($i) => now()->subDays($i)->format('M d'))->toArray();
            } else {
                $percentages = $data['percentages'] ?? [];
                $dates = $data['dates'] ?? collect(range(6, 0, -1))->map(fn($i) => now()->subDays($i)->format('M d'))->toArray();
            }
            
            return [
                'datasets' => [
                    [
                        'label' => 'Closure %',
                        'data' => $percentages,
                        'backgroundColor' => 'rgba(47, 79, 47, 0.12)',
                        'borderColor' => '#2F4F2F',
                        'fill' => true,
                    ],
                ],
                'labels' => $dates,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                ],
            ],
        ];
    }
}
