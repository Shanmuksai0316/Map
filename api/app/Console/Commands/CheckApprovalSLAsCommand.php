<?php

namespace App\Console\Commands;

use App\Services\Approvals\ApprovalSLAService;
use Illuminate\Console\Command;

class CheckApprovalSLAsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approvals:check-sla {--tenant= : Check SLA for specific tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check approval SLAs and send notifications for breaches and warnings';

    /**
     * Execute the console command.
     */
    public function handle(ApprovalSLAService $slaService)
    {
        $this->info('Checking approval SLAs...');

        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $this->info("Checking SLAs for tenant: {$tenantId}");
        }

        $slaService->checkAndNotify();

        $this->info('SLA check completed successfully.');
    }
}
