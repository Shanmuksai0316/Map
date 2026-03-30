<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Room Model (Single Shared Database)
 * 
 * Stored in central database with tenant_id scoping.
 * Tenant isolation is enforced via TenantScope global scope and PostgreSQL RLS.
 */
class Room extends Model
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use HasFactory;
    use Traits\TenantScoped;

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'hostel_id',
        'floor_id',
        'block_code',
        'floor_code',
        'room_no',
        'number',
        'capacity',
        'room_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    /**
     * Room type constants.
     */
    public const ROOM_TYPES = [
        'single' => 'Single (1 bed)',
        'double' => 'Double (2 beds)',
        'triple' => 'Triple (3 beds)',
        'quad' => 'Quad (4 beds)',
    ];

    /**
     * Get the tenant this room belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * Get the floor this room is on.
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(RoomBed::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class);
    }

    /**
     * Get the display name for this room.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->room_no ?? $this->number ?? "Room {$this->id}";
    }

    /**
     * Get room type label.
     */
    public function getRoomTypeLabelAttribute(): string
    {
        return self::ROOM_TYPES[$this->room_type] ?? ucfirst($this->room_type ?? 'single');
    }

    /**
     * Get the number of available beds.
     */
    public function getAvailableBedsCountAttribute(): int
    {
        return $this->beds()->where('status', 'available')->count();
    }

    /**
     * Get the number of occupied beds.
     */
    public function getOccupiedBedsCountAttribute(): int
    {
        return $this->beds()->where('status', 'occupied')->count();
    }
}
