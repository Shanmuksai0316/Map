<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelChangeLog extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'changed_by',
        'changes',
        'reason',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
