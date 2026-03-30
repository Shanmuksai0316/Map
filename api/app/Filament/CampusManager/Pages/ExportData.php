<?php

namespace App\Filament\CampusManager\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExportData extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Legacy Export';

    protected static ?int $navigationSort = 21;

    protected static bool $shouldRegisterNavigation = false; // Hidden — replaced by ReportCenter

    protected static string $view = 'filament.campus-manager.pages.export-data';

    protected static ?string $title = 'Data Export';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Export Configuration')
                    ->description('Select the data you want to export and configure the export parameters')
                    ->schema([
                        Forms\Components\Select::make('export_type')
                            ->label('Export Type')
                            ->options([
                                'students' => 'Students',
                                'outpasses' => 'Out-Passes',
                                'attendance' => 'Attendance Sessions',
                                'tickets' => 'Tickets',
                                'rooms' => 'Rooms & Allocations',
                                'notices' => 'Notices',
                                'gate_entries' => 'Gate Entries',
                                'incidents' => 'Incidents',
                                'payments' => 'Payments',
                                'laundry' => 'Laundry Requests',
                                'sports' => 'Sports Events',
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->maxDate(now())
                                    ->native(false)
                                    ->helperText('Leave empty to include all records'),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->maxDate(now())
                                    ->after('start_date')
                                    ->native(false)
                                    ->helperText('Leave empty to include up to today'),
                            ]),

                        Forms\Components\Select::make('hostel_id')
                            ->label('Filter by Hostel')
                            ->relationship('hostel', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to include all hostels')
                            ->visible(fn (Forms\Get $get): bool => in_array(
                                $get('export_type'),
                                ['students', 'outpasses', 'attendance', 'rooms', 'laundry', 'sports']
                            )),

                        Forms\Components\Select::make('status')
                            ->label('Filter by Status')
                            ->options(function (Forms\Get $get) {
                                return match ($get('export_type')) {
                                    'outpasses' => [
                                        'pending' => 'Pending',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        'expired' => 'Expired',
                                    ],
                                    'tickets' => [
                                        'open' => 'Open',
                                        'in_progress' => 'In Progress',
                                        'resolved' => 'Resolved',
                                        'closed' => 'Closed',
                                    ],
                                    'incidents' => [
                                        'open' => 'Open',
                                        'closed' => 'Closed',
                                    ],
                                    default => [],
                                };
                            })
                            ->searchable()
                            ->native(false)
                            ->helperText('Leave empty to include all statuses')
                            ->visible(fn (Forms\Get $get): bool => in_array(
                                $get('export_type'),
                                ['outpasses', 'tickets', 'incidents']
                            )),

                        Forms\Components\Select::make('format')
                            ->label('Export Format')
                            ->options([
                                'csv' => 'CSV',
                                'xlsx' => 'Excel (XLSX)',
                            ])
                            ->default('csv')
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make('include_deleted')
                            ->label('Include Deleted Records')
                            ->helperText('Include soft-deleted records in the export')
                            ->default(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function export(): void
    {
        $data = $this->form->getState();

        // Validate required fields
        if (empty($data['export_type'])) {
            Notification::make()
                ->danger()
                ->title('Export Type Required')
                ->body('Please select the type of data to export.')
                ->send();
            return;
        }

        // Check permissions based on export type
        if (!$this->canExport($data['export_type'])) {
            Notification::make()
                ->danger()
                ->title('Permission Denied')
                ->body('You do not have permission to export this data.')
                ->send();
            return;
        }

        try {
            // Get tenant ID (ExportJob is in central DB)
            $tenant = tenancy()->tenant;
            if (!$tenant) {
                throw new \Exception('No tenant context available');
            }
            
            // Create export job in central database
            $exportJob = \App\Models\ExportJob::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::id(),
                'type' => $data['export_type'],
                'filters' => array_filter([
                    'start_date' => $data['start_date'] ?? null,
                    'end_date' => $data['end_date'] ?? null,
                    'hostel_id' => $data['hostel_id'] ?? null,
                    'status' => $data['status'] ?? null,
                    'format' => $data['format'] ?? 'csv',
                    'include_deleted' => $data['include_deleted'] ?? false,
                ]),
                'status' => 'Queued',
            ]);
            
            // Dispatch job for processing
            \App\Jobs\GenerateExportJob::dispatch($exportJob);
            
            Notification::make()
                ->success()
                ->title('Export Queued')
                ->body("Export job #{$exportJob->id} has been queued. You'll receive a notification when ready.")
                ->send();

            // Log the export request
            Log::info('Export requested', [
                'export_job_id' => $exportJob->id,
                'user_id' => Auth::id(),
                'tenant_id' => $tenant->id,
                'export_type' => $data['export_type'],
                'filters' => $exportJob->filters,
            ]);
            
            // Optional: Log to audit
            if (class_exists(\App\Services\AuditLogger::class)) {
                app(\App\Services\AuditLogger::class)->logEvent(
                    'export.queued',
                    ['export_id' => $exportJob->id, 'type' => $data['export_type']]
                );
            }

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Export Failed')
                ->body('An error occurred while creating the export: ' . $e->getMessage())
                ->send();
            
            Log::error('Export creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function canExport(string $exportType): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Campus Manager cannot export (view only); export is for Super Admin only.
        if ($user->hasRole('Campus Manager')) {
            return false;
        }

        // Add role-specific permissions
        return match ($exportType) {
            'students', 'rooms' => $user->can('student.export'),
            'outpasses' => $user->can('outpass.export'),
            'attendance' => $user->can('attendance.export'),
            'tickets' => $user->can('ticket.export'),
            default => false,
        };
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        // Campus Manager can see student details but must not have export option (no Reports/Data Export).
        if (!$user || !Auth::check()) {
            return false;
        }
        return $user->hasRole('Super Admin');
    }
}

