<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantActivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public array $wizardData = [],
    ) {}
}
