<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Account Deletion Request
 *
 * Tracks user-initiated account deletion requests.
 * Status: requested (immediate) -> processing (manual/job) -> completed.
 */
class AccountDeletionRequest extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'tenant_id',
        'status',
        'requested_at',
        'processed_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateId(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
