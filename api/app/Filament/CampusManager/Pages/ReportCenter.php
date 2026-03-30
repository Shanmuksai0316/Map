<?php

namespace App\Filament\CampusManager\Pages;

use App\Models\Hostel;
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
use Maatwebsite\Excel\Facades\Excel;

class ReportCenter extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.campus-manager.pages.report-center';

    public ?string $reportType = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?int $hostelId = null;

    public ?string $format = 'csv';

    public function mount(): void
    {
        $this->startDate = now()->subDays(7)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        $tenantId = Auth::user()?->tenant_id;

        return $form
            ->schema([
                Select::make('reportType')
                    ->label('Report Type')
                    ->required()
                    ->options([
                        'housekeeping' => 'Housekeeping Requests',
                        'maintenance' => 'Maintenance Requests',
                        'pass_requests' => 'Pass Requests (Outpass/Leave)',
                        'attendance' => 'Attendance Summary',
                        'attendance_detail' => 'Attendance Detail (with absent student names)',
                        'checklist' => 'Checklist Compliance',
                        'room_occupancy' => 'Room Occupancy',
                        'guest_visits' => 'Guest Visit Log',
                        'incidents' => 'Incident Summary',
                    ])
                    ->native(false)
                    ->live(),
                Select::make('hostelId')
                    ->label('Tenant')
                    ->options(function () use ($tenantId) {
                        if (! $tenantId) {
                            return [];
                        }

                        return ['' => 'All'] + Hostel::where('tenant_id', $tenantId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
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
            ->columns(5);
    }

    public function download()
    {
        if (! $this->reportType) {
            Notification::make()
                ->danger()
                ->title('Select a report type')
                ->send();

            return null;
        }

        try {
            $from = Carbon::parse($this->startDate);
            $to = Carbon::parse($this->endDate);
            $service = app(ReportService::class);
            $tenantId = Auth::user()->tenant_id;
            $hostelId = $this->hostelId ?: (session('active_hostel_id') ?: null);

            $data = match ($this->reportType) {
                'housekeeping' => $service->housekeepingRequests($tenantId, $hostelId, $from, $to),
                'maintenance' => $service->maintenanceRequests($tenantId, $hostelId, $from, $to),
                'pass_requests' => $service->passRequests($tenantId, $hostelId, $from, $to),
                'attendance' => $service->attendanceSummary($tenantId, $hostelId, $from, $to),
                'attendance_detail' => $service->attendanceDetailReport($tenantId, $hostelId, $from, $to),
                'checklist' => $service->checklistCompliance($tenantId, $hostelId, $from, $to),
                'room_occupancy' => $service->roomOccupancy($tenantId, $hostelId),
                'guest_visits' => $service->guestVisitLog($tenantId, $hostelId, $from, $to),
                'incidents' => $service->incidentSummary($tenantId, $hostelId, $from, $to),
                default => collect(),
            };

            if ($data->isEmpty()) {
                Notification::make()
                    ->warning()
                    ->title('No data found')
                    ->body('No records found for the selected criteria.')
                    ->send();

                return null;
            }

            $filename = $this->reportType . '_' . $from->format('Ymd') . '_' . $to->format('Ymd');
            $extension = $this->format === 'xlsx' ? 'xlsx' : 'csv';

            return response()->streamDownload(function () use ($data, $extension) {
                $output = fopen('php://output', 'w');

                // Write headers
                fputcsv($output, array_keys($data->first()));

                // Write rows
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }

                fclose($output);
            }, "{$filename}.{$extension}");
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title('Invalid date range')
                ->body($e->getMessage())
                ->send();

            return null;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Report generation failed', [
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
}
