<?php

namespace App\Filament\Rector\Pages;

use App\Services\Reports\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.rector.pages.reports';

    protected static ?string $title = 'Report Center';

    public ?string $reportType = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $format = 'csv';

    public function mount(): void
    {
        $this->startDate = now()->subDays(7)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('reportType')
                    ->label('Report Type')
                    ->required()
                    ->options([
                        'approval_summary' => 'Approval Summary (Pass/Leave)',
                        'attendance' => 'Attendance Summary',
                        'attendance_detail' => 'Attendance Detail (with absent names)',
                        'checklist' => 'Checklist Compliance',
                        'incidents' => 'Incident Summary',
                        'pass_requests' => 'Pass Requests Detail',
                    ])
                    ->native(false),
                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->required()
                    ->maxDate(now())
                    ->native(false),
                DatePicker::make('endDate')
                    ->label('End Date')
                    ->required()
                    ->maxDate(now())
                    ->native(false),
                Select::make('format')
                    ->label('Format')
                    ->options([
                        'csv' => 'CSV',
                        'xlsx' => 'XLSX',
                    ])
                    ->default('csv')
                    ->required()
                    ->native(false),
            ])
            ->columns(4);
    }

    public function download()
    {
        if (! $this->reportType) {
            Notification::make()->danger()->title('Select a report type')->send();

            return null;
        }

        try {
            $from = Carbon::parse($this->startDate);
            $to = Carbon::parse($this->endDate);
            $service = app(ReportService::class);
            $tenantId = Auth::user()->tenant_id;

            $data = match ($this->reportType) {
                'approval_summary' => $service->approvalSummary($tenantId, $from, $to),
                'attendance' => $service->attendanceSummary($tenantId, null, $from, $to),
                'attendance_detail' => $service->attendanceDetailReport($tenantId, null, $from, $to),
                'checklist' => $service->checklistCompliance($tenantId, null, $from, $to),
                'incidents' => $service->incidentSummary($tenantId, null, $from, $to),
                'pass_requests' => $service->passRequests($tenantId, null, $from, $to),
                default => collect(),
            };

            if ($data->isEmpty()) {
                Notification::make()->warning()->title('No data found')->send();

                return null;
            }

            $filename = 'rector_' . $this->reportType . '_' . $from->format('Ymd') . '_' . $to->format('Ymd');

            return response()->streamDownload(function () use ($data) {
                $output = fopen('php://output', 'w');
                fputcsv($output, array_keys($data->first()));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            }, "{$filename}.csv");
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Report failed')->body($e->getMessage())->send();

            return null;
        }
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->hasRole('Rector');
    }
}
