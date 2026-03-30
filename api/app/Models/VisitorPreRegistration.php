<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitorPreRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'hostel_id',
        'student_id',
        'guest_name',
        'guest_phone',
        'person_to_meet',
        'visiting_date',
        'status',
    ];

    protected $casts = [
        'visiting_date' => 'date',
    ];

    /**
     * Tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VisitorLog::class, 'pre_registration_id');
    }

    /**
     * Check if pre-registration is valid for today
     */
    public function isValidForToday(): bool
    {
        return $this->status === 'Pending' && 
               ($this->visiting_date === null || $this->visiting_date->isToday());
    }

    /**
     * Approve the pre-registration
     */
    public function approve(): void
    {
        $this->update(['status' => 'Approved']);
    }

    /**
     * Deny the pre-registration
     */
    public function deny(): void
    {
        $this->update(['status' => 'Denied']);
    }

    /**
     * Expire old pre-registrations
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'Pending')
                     ->where('visiting_date', '<', now()->toDateString());
    }
}

