<?php

namespace App\Console\Commands;

use App\Services\OutPassExpiryService;
use Illuminate\Console\Command;

class ExpireOutPasses extends Command
{
    protected $signature = 'outpasses:expire';

    protected $description = 'Mark approved out-passes as expired after valid_until';

    public function handle(OutPassExpiryService $expiryService): int
    {
        $count = $expiryService->expireOutPasses();
        $this->info("Expired {$count} out-pass(es).");

        return self::SUCCESS;
    }
}

