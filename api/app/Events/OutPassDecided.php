<?php

namespace App\Events;

use App\Models\Domain\OutPass\OutPass;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutPassDecided
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public OutPass $outPass
    ) {}
}
