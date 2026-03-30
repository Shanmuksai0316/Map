<?php

namespace App\Models\Domain\OutPass;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutPassExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'requested_by',
        'status',
        'filters',
        'file_path',
        'error',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_FAILED = 'failed';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
