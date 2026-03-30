<?php

namespace App\Jobs;

use App\Models\Domain\OutPass\OutPass;
use App\Models\Domain\OutPass\OutPassExport;
use App\Support\OutPassExportFilename;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class ExportOutPassesJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $exportId)
    {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $export = OutPassExport::findOrFail($this->exportId);

        // Set tenant session variable for RLS policies
        \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($export->tenant_id);

        $export->update(['status' => OutPassExport::STATUS_PROCESSING]);

        try {
            $writer = Writer::createFromString('');
            $writer->insertOne([
                'ID',
                'Student',
                'Hostel',
                'Reason',
                'Overnight',
                'Status',
                'Requested At',
                'Valid Until',
                'Decided At',
                'Note',
            ]);

            OutPass::query()
                ->with(['student.user', 'hostel'])
                ->where('tenant_id', $export->tenant_id)
                ->when($export->filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->when($export->filters['from'] ?? null, fn ($q, $from) => $q->whereDate('requested_at', '>=', $from))
                ->when($export->filters['to'] ?? null, fn ($q, $to) => $q->whereDate('requested_at', '<=', $to))
                ->orderBy('requested_at', 'desc')
                ->chunkById(500, function ($chunk) use (&$writer): void {
                    foreach ($chunk as $outPass) {
                        /** @var OutPass $outPass */
                        $writer->insertOne([
                            $outPass->id,
                            $outPass->student?->user?->name,
                            $outPass->hostel?->name,
                            $outPass->reason->label(),
                            $outPass->overnight ? 'Yes' : 'No',
                            $outPass->status->label(),
                            optional($outPass->requested_at)->toIso8601String(),
                            optional($outPass->valid_until)->toIso8601String(),
                            optional($outPass->decided_at)->toIso8601String(),
                            $outPass->note,
                        ]);
                    }
                });

            $tenantCode = 'tenant_'.$export->tenant_id;
            $path = OutPassExportFilename::forTenant($tenantCode);
            Storage::disk('exports')->put($path, $writer->toString());

            $export->update([
                'status' => OutPassExport::STATUS_COMPLETE,
                'file_path' => $path,
            ]);
        } catch (\Throwable $exception) {
            $export->update([
                'status' => OutPassExport::STATUS_FAILED,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            // Clear tenant session variable after job completion
            \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
        }
    }
}
