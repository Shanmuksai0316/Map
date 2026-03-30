<?php

namespace App\Domain\Leaves\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'leave_id',
        'acted_by',
        'from_status',
        'to_status',
        'note',
        'timeline_label',
        'timeline_description',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
