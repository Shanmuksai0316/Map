<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sports Blockout Model (Tenant Database)
 * 
 * Represents time periods when a sports facility is unavailable (maintenance, events, etc.).
 * Stores in tenant database - automatic isolation by database.
 * NO tenant_id needed with database-per-tenant architecture.
 */
class SportsBlockout extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'start_at',
        'end_at',
        'reason',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the facility this blockout belongs to
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(SportsFacility::class, 'facility_id');
    }

    /**
     * Get the user who created this blockout
     */
    public function creator(): BelongsTo
    {
        // Cross-database relationship: SportsBlockout (tenant DB) -> User (central DB)
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this blockout overlaps with a given time range
     */
    public function overlaps($startTime, $endTime): bool
    {
        return $this->start_at < $endTime && $this->end_at > $startTime;
    }

    /**
     * Check if this blockout is currently active
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->start_at <= $now && $this->end_at >= $now;
    }

    /**
     * Check if this blockout is in the future
     */
    public function isFuture(): bool
    {
        return $this->start_at > now();
    }

    /**
     * Check if this blockout has passed
     */
    public function isPast(): bool
    {
        return $this->end_at < now();
    }
}
