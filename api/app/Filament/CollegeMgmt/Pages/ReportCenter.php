<?php

namespace App\Filament\CollegeMgmt\Pages;

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
use Illuminate\Support\Collection;

class ReportCenter extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.college-mgmt.pages.report-center';

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
                        'attendance' => 'Attendance Summary',
                        'attendance_detail' => 'Attendance Detail (with absent names)',
                        'pass_requests' => 'Pass Requests (Outpass/Leave)',
                        'incidents' => 'Incident Summary',
                        'room_occupancy' => 'Room Occupancy',
                        'checklist' => 'Checklist Compliance',
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
            if ($from->gt($to)) {
                Notification::make()
                    ->danger()
                    ->title('Invalid date range')
                    ->body('Start date must be before or equal to end date.')
                    ->send();

                return null;
            }

            $service = app(ReportService::class);
            $tenantId = Auth::user()?->tenant_id ?: (function_exists('tenant') ? tenant()?->id : null);

            if (!$tenantId) {
                Notification::make()
                    ->danger()
                    ->title('Tenant not found')
                    ->body('Please login from a valid tenant domain and try again.')
                    ->send();

                return null;
            }

            $data = match ($this->reportType) {
                'attendance' => $service->attendanceSummary($tenantId, null, $from, $to),
                'attendance_detail' => $service->attendanceDetailReport($tenantId, null, $from, $to),
                'pass_requests' => $service->passRequests($tenantId, null, $from, $to),
                'incidents' => $service->incidentSummary($tenantId, null, $from, $to),
                'room_occupancy' => $service->roomOccupancy($tenantId, null),
                'checklist' => $service->checklistCompliance($tenantId, null, $from, $to),
                default => collect(),
            };

            $filename = 'college_mgmt_' . $this->reportType . '_' . $from->format('Ymd') . '_' . $to->format('Ymd');
            [$headings, $rows] = $this->normalizeExportData($data, $this->reportType);

            if (empty($headings)) {
                Notification::make()
                    ->warning()
                    ->title('No exportable data')
                    ->body('Unable to build report rows for the selected criteria.')
                    ->send();

                return null;
            }

            if ($this->format === 'xlsx') {
                Notification::make()
                    ->warning()
                    ->title('XLSX unavailable')
                    ->body('XLSX export is temporarily unavailable. Downloading CSV instead.')
                    ->send();
            }

            return response()->streamDownload(function () use ($headings, $rows): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, $headings);
                foreach ($rows as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            }, "{$filename}.csv", [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title('Invalid date range')
                ->body($e->getMessage())
                ->send();

            return null;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('College Mgmt Report generation failed', [
                'type' => $this->reportType,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('Report generation failed')
                ->body('An error occurred. Please try again.')
                ->send();

            return null;
        }
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole('College Management') || $user->hasRole('College Mgmt'));
    }

    private function normalizeExportData(Collection $data, ?string $reportType = null): array
    {
        if ($data->isEmpty()) {
            return [$this->defaultHeadingsForReport($reportType), collect()];
        }

        $first = $data->first();
        if (is_object($first)) {
            $first = (array) $first;
        }
        if (!is_array($first)) {
            return [[], collect()];
        }

        $headings = array_keys($first);
        $rows = $data->map(function ($row) use ($headings) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (!is_array($row)) {
                $row = [];
            }

            return collect($headings)
                ->map(fn (string $key) => $this->normalizeCellValue($row[$key] ?? null))
                ->all();
        });

        return [$headings, $rows];
    }

    private function defaultHeadingsForReport(?string $reportType): array
    {
        return match ($reportType) {
            'attendance' => ['Date', 'Hostel', 'Session Type', 'Total', 'Present', 'Absent', 'On Leave', 'Attendance %', 'Status'],
            'attendance_detail' => ['Date', 'Hostel', 'Session Type', 'Total Students', 'Present', 'Absent', 'On Leave', 'Attendance %', 'Status', 'Absent Student Names', 'Absent Student IDs'],
            'pass_requests' => ['Date', 'Student', 'Type', 'Reason', 'Status', 'Decided By', 'Valid Until'],
            'incidents' => ['Date', 'Hostel', 'Type', 'Severity', 'Status', 'Reported By'],
            'room_occupancy' => ['Hostel', 'Floor', 'Room', 'Type', 'Capacity', 'Occupied', 'Available'],
            'checklist' => ['Date', 'Staff Name', 'Role', 'Checklist', 'Total Items', 'Completed', 'Completion %', 'Status', 'Submitted At'],
            default => [],
        };
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_object($value)) {
            return method_exists($value, '__toString')
                ? (string) $value
                : json_encode($value);
        }

        return $value;
    }
}
