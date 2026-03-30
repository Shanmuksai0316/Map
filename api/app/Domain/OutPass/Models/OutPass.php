<?php

namespace App\Domain\OutPass\Models;

use App\Enums\OutPassType;

/**
 * Compatibility wrapper so tests and legacy code using
 * App\Domain\OutPass\Models\OutPass resolve to the central model.
 * We override casts so that status is exposed as a scalar string for assertions.
 */
class OutPass extends \App\Models\Domain\OutPass\OutPass
{
    protected $casts = [
        'reason' => OutPassType::class,
        'status' => 'string',
        'overnight' => 'boolean',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'valid_until' => 'datetime',
    ];
}

