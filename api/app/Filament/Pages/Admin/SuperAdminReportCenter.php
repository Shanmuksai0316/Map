<?php

namespace App\Filament\Pages\Admin;

use App\Models\Hostel;
use App\Models\Tenant;
use App\Services\Reports\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SuperAdminReportCenter extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Report Center';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Report Center';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.admin.super-admin-report-center';

    public ?string $reportType = null;

    public ?string $tenantId = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $format = 'csv';

    public function mount(): void
    {
        $this->startDate = now()->subDays(7)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public static function canAccess(): bool
    {
        $featureEnabled = config('features.super_admin_reports', true);

        try {
            $dbSetting = \App\Models\SystemSetting::get('feature_flags.super_admin_reports', $featureEnabled);
            $featureEnabled = filter_var($dbSetting, FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable) {
            // Fallback to config value when settings table/entry is unavailable.
        }

        return (Auth::user()?->hasRole('Super Admin') ?? false) && $featureEnabled;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                        'xl' => 5,
                    ])
                    ->schema([
                        Select::make('reportType')
                            ->label('Report Type')
                            ->required()
                            ->options([
                                'tenant_overview' => 'Tenant Overview',
                                'occupancy' => 'Occupancy Report',
                                'student_export' => 'Student Data Export',
                                'staff_deployment' => 'Staff Deployment',
                                'attendance' => 'Attendance Compliance',
                                'requests' => 'Request Summary',
                                'incidents' => 'Incident Report',
                                'checkout_renewal' => 'Checkout/Renewal',
                                'payments' => 'Payment Collections',
                                'audit_trail' => 'Audit Trail',
                            ])
                            ->native(false)
                            ->live(),
                        Select::make('tenantId')
                            ->label('Tenant')
                            ->options(fn () => ['' => 'All Tenants'] + Tenant::orderBy('name')->pluck('name', 'id')->toArray())
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
                    ]),
            ]);
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
            if ($from->isAfter($to)) {
                Notification::make()
                    ->danger()
                    ->title('Invalid date range')
                    ->body('Start date must be before or equal to end date.')
                    ->send();

                return null;
            }

            if ($from->diffInDays($to) > ReportService::MAX_RANGE_DAYS) {
                Notification::make()
                    ->danger()
                    ->title('Invalid date range')
                    ->body('Date range cannot exceed ' . ReportService::MAX_RANGE_DAYS . ' days.')
                    ->send();

                return null;
            }

            $service = app(ReportService::class);
            $tenantId = $this->tenantId ?: null;

            $data = match ($this->reportType) {
                'tenant_overview' => $service->tenantOverview(),
                'occupancy' => $service->occupancyReport($tenantId),
                'staff_deployment' => $service->staffDeployment($tenantId),
                'attendance' => $service->attendanceSummary($tenantId, null, $from, $to),
                'requests' => $service->requestSummary($tenantId, $from, $to),
                'incidents' => $service->incidentSummary($tenantId, null, $from, $to),
                'checkout_renewal' => $service->checkoutRenewal($tenantId, $from, $to),
                'payments' => $service->paymentCollections($tenantId, $from, $to),
                'audit_trail' => $service->auditTrail($tenantId, $from, $to),
                'student_export' => $this->studentExport($tenantId, $from, $to),
                default => collect(),
            };

            $filename = $this->reportType . '_' . $from->format('Ymd') . '_' . $to->format('Ymd');
            $headers = $this->resolveHeaders($data, $this->reportType);

            return response()->streamDownload(function () use ($data, $headers) {
                $output = fopen('php://output', 'w');
                fputcsv($output, $headers);
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

    protected function resolveHeaders(\Illuminate\Support\Collection $data, string $reportType): array
    {
        if ($data->isNotEmpty()) {
            return array_keys((array) $data->first());
        }

        return match ($reportType) {
            'tenant_overview' => ['Tenant Code', 'Tenant Name', 'Status', 'Hostels', 'Students', 'Staff'],
            'occupancy' => ['Tenant', 'Hostel', 'Gender Mode', 'Total Beds', 'Occupied', 'Available', 'Occupancy %'],
            'student_export' => ['Student UID', 'Name', 'Gender', 'Email', 'Phone', 'Hostel', 'Program', 'Year', 'Department'],
            'staff_deployment' => ['Staff Name', 'Phone', 'Tenant', 'Hostel', 'Role', 'Assigned Since'],
            'attendance' => ['Date', 'Hostel', 'Session Type', 'Total', 'Present', 'Absent', 'On Leave', 'Attendance %'],
            'requests' => ['Date', 'Request Type', 'Reference', 'Tenant', 'Hostel', 'Category', 'Status', 'Priority'],
            'incidents' => ['Date', 'Hostel', 'Category', 'Severity', 'Status', 'Reported By', 'Resolved At'],
            'checkout_renewal' => ['Date', 'Tenant', 'Student', 'Hostel', 'Bed ID', 'Expected Checkout', 'Checkout Status', 'Active Allocation'],
            'payments' => ['Date', 'Tenant', 'Student', 'Reference', 'Amount', 'Currency', 'Mode', 'Status'],
            'audit_trail' => ['DateTime', 'Tenant', 'User', 'Action', 'Entity Type', 'Entity ID'],
            default => ['No Data'],
        };
    }

    protected function studentExport(?string $tenantId, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return \App\Models\Student::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$from, $to])
            ->with(['user', 'hostel'])
            ->get()
            ->map(fn ($s) => [
                'Student UID' => $s->student_uid,
                'Name' => $s->user?->name ?? $s->full_name,
                'Gender' => $s->gender ?? '—',
                'Email' => $s->email_address ?? '—',
                'Phone' => $s->mobile_number ?? '—',
                'Hostel' => $s->hostel?->name ?? '—',
                'Program' => $s->program ?? '—',
                'Year' => $s->year_of_study ?? '—',
                'Department' => $s->department ?? '—',
            ]);
    }
}
