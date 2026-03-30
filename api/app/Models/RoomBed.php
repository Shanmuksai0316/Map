<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\TenantScoped;

class RoomBed extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'tenant_id',
'room_id',
        'hostel_id',
        'code',
        'status',
        'occupied_at',
        'released_at',
        'meta',
    ];

    protected $casts = [
        'occupied_at' => 'datetime',
        'released_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Get the tenant this model belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class);
    }

    public function blockedPeriods(): HasMany
    {
        return $this->hasMany(RoomBlockedBed::class);
    }
}
