<?php

namespace App\Console\Commands;

use App\Domain\OutPass\Models\OutPass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOutPassesCommand extends Command
{
    protected $signature = 'app:expire-outpasses';

    protected $description = 'Expire pending out-passes that are older than 24 hours';

    public function handle(): int
    {
        $this->info('Starting out-pass expiry job...');

        // Find all Pending out-passes where requested_at + 24h < now
        $expiredCount = OutPass::where('status', 'Pending')
            ->where('requested_at', '<', now()->subHours(24))
            ->update([
                'status' => 'Expired',
                'updated_at' => now(),
            ]);

        $this->info("Expired {$expiredCount} out-passes");

        return Command::SUCCESS;
    }
}
