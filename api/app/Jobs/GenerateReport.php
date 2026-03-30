<?php

namespace App\Jobs;

use App\Services\Reports\Reports;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $reportId
    ) {
    }

    public function handle(): void
    {
        $report = DB::table('reports')->find($this->reportId);
        if (!$report) {
            return;
        }

        // Update status to running
        DB::table('reports')
            ->where('id', $this->reportId)
            ->update(['status' => 'running']);

        try {
            // Generate CSV content
            $csvContent = $this->generateCsv($report->name, json_decode($report->params, true));
            
            // Save to storage
            $filename = "reports/{$report->name}_{$this->reportId}_" . now()->format('Y-m-d_H-i-s') . '.csv';
            Storage::put($filename, $csvContent);
            
            // Update report with storage path
            DB::table('reports')
                ->where('id', $this->reportId)
                ->update([
                    'status' => 'done',
                    'storage_path' => $filename,
                ]);
                
        } catch (\Exception $e) {
            // Update report with error
            DB::table('reports')
                ->where('id', $this->reportId)
                ->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
        }
    }

    private function generateCsv(string $reportName, array $params): string
    {
        $generator = Reports::run($reportName, $params);
        
        $rows = [];
        foreach ($generator as $row) {
            $rows[] = $row;
        }
        
        if (empty($rows)) {
            return "No data found\n";
        }
        
        // Convert to CSV
        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($rows[0]));
        
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}