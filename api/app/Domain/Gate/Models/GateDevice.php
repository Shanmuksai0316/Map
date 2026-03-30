<?php

namespace App\Domain\Gate\Models;

use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GateDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'device_uuid',
        'name',
        'is_active',
        'enrolled_by_user_id',
        'enrolled_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'enrolled_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by_user_id');
    }

    public function scopeForHostel(Builder $query, int $hostelId): Builder
    {
        return $query->where('hostel_id', $hostelId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

