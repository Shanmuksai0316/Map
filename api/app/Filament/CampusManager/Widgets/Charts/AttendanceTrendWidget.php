<?php

namespace App\Filament\CampusManager\Widgets\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Attendance Trend (7 Days)';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected bool $hasData = false;

    protected static ?int $sort = 3;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $service = app(DashboardDataService::class);

        // Prefer tenant context from subdomain (tenant()) and fall back to user. This
        // matches how the Campus Manager dashboard heading resolves tenant context.
        $tenantId = null;
        try {
            if (function_exists('tenant') && tenant()) {
                $tenantId = tenant()->id;
            }
        } catch (\Throwable $e) {
            // tenant() helper may not be available or tenant not resolved
        }

        if (! $tenantId) {
            $tenantId = Auth::user()?->tenant_id;
        }
        $hostelId = session('active_hostel_id');

        // Use global time range from session, default to 7 days
        $days = (int) session('dashboard_range_days', 7);
        $from = now()->subDays($days);
        $to = now();

        if (! $tenantId) {
            $this->hasData = false;

            return [
                'datasets' => [
                    [
                        'label' => 'Attendance %',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $data = $service->attendanceTrend($tenantId, $hostelId, $from, $to);

        $values = $data['data'] ?? [];
        $this->hasData = collect($values)->filter(fn ($v) => $v !== null)->isNotEmpty();

        return [
            'datasets' => [
                [
                    'label' => $this->hasData ? 'Attendance %' : 'No attendance data for selected range',
                    'data' => $values,
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'spanGaps' => true,
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }

    public function getHeading(): ?string
    {
        $base = static::$heading ?? 'Attendance Trend (7 Days)';

        if (! $this->hasData) {
            return $base . ' — No data for selected range';
        }

        return $base;
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'min' => 0,
                    'max' => 100,
                    'ticks' => ['callback' => 'function(value) { return value + "%"; }'],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.parsed.y + "%"; }',
                    ],
                ],
            ],
        ];
    }
}
