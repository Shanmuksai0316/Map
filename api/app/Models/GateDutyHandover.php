<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateDutyHandover extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'hostel_id',
        'guard_id',
        'shift_start',
        'shift_end',
        'notes',
        'status',
        'incidents_count',
        'entries_processed',
        'issues_reported',
    ];

    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'incidents_count' => 'integer',
        'entries_processed' => 'integer',
    ];

    /**
     * Get the tenant this model belongs to.
     * With database-per-tenant, tenant context is automatic.
     */
    public function tenant(): ?\App\Models\Tenant
    {
        return tenancy()->tenant;
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function guardUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_id');
    }

    /**
     * Check if this handover is for today
     */
    public function isToday(): bool
    {
        return $this->shift_start->isToday() || $this->shift_end->isToday();
    }

    /**
     * Check if this handover is currently active
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->shift_start->lte($now) && $this->shift_end->gte($now);
    }

    /**
     * Check if this handover is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get the duration of the shift in hours
     */
    public function getShiftDuration(): float
    {
        return $this->shift_start->diffInHours($this->shift_end);
    }

    /**
     * Mark handover as completed
     */
    public function markCompleted(string $notes = null): bool
    {
        $this->status = 'completed';
        if ($notes) {
            $this->notes = ($this->notes ? $this->notes . "\n\nCompletion Notes: " : '') . $notes;
        }

        return $this->save();
    }
}
