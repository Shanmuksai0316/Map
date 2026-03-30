<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
        'hostel_id',
        'type',
        'student_id',
        'note',
        'status',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'closure_note',
        'metadata',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'metadata' => 'array',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Incident types
     */
    public const TYPE_LATE_RETURN = 'LateReturn';
    public const TYPE_MISSED_ATTENDANCE = 'MissedAttendance';
    public const TYPE_EMERGENCY_EXIT = 'EmergencyExit';
    public const TYPE_SECURITY = 'Security';
    public const TYPE_MEDICAL = 'Medical';

    /**
     * Get all incident types
     */
    public static function types(): array
    {
        return [
            self::TYPE_LATE_RETURN,
            self::TYPE_MISSED_ATTENDANCE,
            self::TYPE_EMERGENCY_EXIT,
            self::TYPE_SECURITY,
            self::TYPE_MEDICAL,
        ];
    }

    /**
     * Get non-medical incident types
     */
    public static function nonMedicalTypes(): array
    {
        return [
            self::TYPE_LATE_RETURN,
            self::TYPE_MISSED_ATTENDANCE,
            self::TYPE_EMERGENCY_EXIT,
            self::TYPE_SECURITY,
        ];
    }

    /**
     * Close the incident
     */
    public function close(int $closedBy, string $closureNote): void
    {
        $this->update([
            'status' => 'Closed',
            'closed_by' => $closedBy,
            'closed_at' => now(),
            'closure_note' => $closureNote,
        ]);
    }

    /**
     * Check if incident is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }

    /**
     * Check if incident is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'Closed';
    }

    /**
     * Check if incident is acknowledged
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Acknowledge the incident
     */
    public function acknowledge(int $userId): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    /**
     * Get the user who acknowledged this incident
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Check if this is a medical incident
     */
    public function isMedical(): bool
    {
        return $this->type === self::TYPE_MEDICAL;
    }

    /**
     * Scope for medical incidents
     */
    public function scopeMedical($query)
    {
        return $query->where('type', self::TYPE_MEDICAL);
    }

    /**
     * Scope for non-medical incidents
     */
    public function scopeNonMedical($query)
    {
        return $query->whereIn('type', self::nonMedicalTypes());
    }

    /**
     * Scope for unacknowledged incidents
     */
    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }
}

