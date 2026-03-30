<?php

namespace App\Filament\Pages\Admin;

use App\Jobs\GenerateReport;
use App\Services\Reports\Reports;
use App\Support\HostelScope;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReportIndex extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $navigationGroup = 'Reports';
    
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.admin.report-index';

    public static function canAccess(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Removed generate report button - now using list view
        ];
    }

    public function generateReportFromAction(array $data): void
    {
        try {
            // Validate date range (max 90 days)
            $fromDate = \Carbon\Carbon::parse($data['from_date']);
            $toDate = \Carbon\Carbon::parse($data['to_date']);
            
            if ($fromDate->diffInDays($toDate) > 90) {
                Notification::make()
                    ->title('Date range too large')
                    ->body('Maximum 90 days allowed')
                    ->danger()
                    ->send();
                return;
            }
            
            // Validate report name
            if (empty($data['report_name'])) {
                Notification::make()
                    ->title('Report name required')
                    ->body('Please select a report type')
                    ->danger()
                    ->send();
                return;
            }
            
            // Check if Reports service has the report
            if (!in_array($data['report_name'], Reports::getAvailableReports())) {
                Notification::make()
                    ->title('Invalid report type')
                    ->body('The selected report type is not available')
                    ->danger()
                    ->send();
                return;
            }
            
            // Create report record with proper error handling
            $reportData = [
                'name' => $data['report_name'],
                'params' => json_encode([
                    'from_date' => $data['from_date'],
                    'to_date' => $data['to_date'],
                    'hostel_id' => $data['hostel_id'] ?? null,
                ]),
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if (auth()->user()->tenant_id) {
                $reportData['tenant_id'] = auth()->user()->tenant_id;
            }
            
            $reportId = DB::table('reports')->insertGetId($reportData);
            
            if (!$reportId) {
                throw new \Exception('Failed to create report record');
            }
            
            // Dispatch job
            GenerateReport::dispatch($reportId);
            
            Notification::make()
                ->title('Report queued')
                ->body('Your report is being generated. Check back in a few minutes.')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            
            Notification::make()
                ->title('Report generation failed')
                ->body('An error occurred while generating the report. Please try again or contact support.')
                ->danger()
                ->send();
        }
    }

    public function getReports()
    {
        $query = DB::table('reports');
        
        // If user has tenant_id, scope to that tenant only
        // Super Admin (no tenant_id) sees all reports
        if (auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }
        
        return $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    public function downloadReport(int $reportId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $report = DB::table('reports')->find($reportId);
        
        // Check access: either Super Admin (no tenant_id) or matching tenant_id
        $hasAccess = !$report ? false : (
            !auth()->user()->tenant_id || // Super Admin can access all
            $report->tenant_id === auth()->user()->tenant_id // Tenant user sees their reports only
        );
        
        if (!$hasAccess) {
            abort(404);
        }
        
        if ($report->status !== 'done' || !$report->storage_path) {
            abort(404);
        }
        
        return Storage::download($report->storage_path);
    }
}
