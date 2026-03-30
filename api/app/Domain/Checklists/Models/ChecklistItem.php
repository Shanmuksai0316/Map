<?php

namespace App\Domain\Checklists\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'instance_id',
        'code',
        'label',
        'require_photo',
        'require_comment',
        'state',
        'comment',
        'photo_urls',
        'completed_at',
    ];

    protected $casts = [
        'require_photo' => 'boolean',
        'require_comment' => 'boolean',
        'photo_urls' => 'array',
        'completed_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ChecklistInstance::class, 'instance_id');
    }
}

