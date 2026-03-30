<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
        'facility_id',
        'student_id',
        'start_at',
        'end_at',
        'status',
        'purpose',
        'participants',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'participants' => 'integer',
    ];

    /**
     * Get the tenant this booking belongs to.
     * With database-per-tenant, tenant context is automatic.
     */
    public function tenant(): ?\App\Models\Tenant
    {
        return tenancy()->tenant;
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SportsFacility::class, 'facility_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Check if the booking can be cancelled
     */
    public function canCancel(): bool
    {
        return $this->status === 'active' && $this->start_at->isFuture();
    }

    /**
     * Check if the booking is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->status === 'active' && $this->start_at->isFuture();
    }

    /**
     * Check if the booking is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->start_at->isPast() &&
               $this->end_at->isFuture();
    }

    /**
     * Check if the booking has ended
     */
    public function hasEnded(): bool
    {
        return $this->end_at->isPast();
    }

    /**
     * Cancel the booking
     */
    public function cancel(): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Mark the booking as completed
     */
    public function complete(): bool
    {
        if (!$this->hasEnded()) {
            return false;
        }

        $this->status = 'completed';
        return $this->save();
    }

    /**
     * Mark as no show
     */
    public function markNoShow(): bool
    {
        if (!$this->hasEnded()) {
            return false;
        }

        $this->status = 'no_show';
        return $this->save();
    }
}
