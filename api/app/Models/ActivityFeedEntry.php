<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityFeedEntry extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'type',
        'channel',
        'related_type',
        'related_id',
        'title',
        'body',
        'metadata',
        'created_by',
        'visibility',
        'notified_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'notified_at' => 'datetime',
    ];

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
