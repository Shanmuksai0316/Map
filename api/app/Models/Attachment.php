<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'user_id',
        'filename',
        'mime_type',
        'size',
        'key',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    /**
     * Tenant relationship.
     *
     * For our single-database, tenant_id–scoped setup, this must return an
     * actual Eloquent relationship instance (not a Tenant object), otherwise
     * Eloquent/Filament will throw a LogicException when accessing $attachment->tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notices(): BelongsToMany
    {
        return $this->belongsToMany(Notice::class, 'notice_attachments')
            ->withTimestamps();
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domain\Tickets\Models\Ticket::class, 'ticket_attachments')
            ->withTimestamps();
    }
}
