<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'floor_number',
        'name',
    ];

    protected $casts = [
        'floor_number' => 'integer',
    ];

    /**
     * Get the tenant that owns the floor.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the hostel that owns the floor.
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * Get the rooms on this floor.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get the total number of beds on this floor.
     */
    public function getTotalBedsAttribute(): int
    {
        return $this->rooms->sum(fn ($room) => $room->beds()->count());
    }

    /**
     * Get the total number of occupied beds on this floor.
     */
    public function getOccupiedBedsAttribute(): int
    {
        return $this->rooms->sum(fn ($room) => $room->beds()->where('status', 'occupied')->count());
    }

    /**
     * Get the display name for this floor.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? "Floor {$this->floor_number}";
    }
}

