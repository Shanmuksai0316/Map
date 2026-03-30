<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'hostel_id',
        'pre_registration_id',
        'guest_name',
        'guest_phone',
        'decision',
        'reason',
        'guard_id',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
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

    public function preRegistration(): BelongsTo
    {
        return $this->belongsTo(VisitorPreRegistration::class, 'pre_registration_id');
    }

    public function guard(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_id');
    }

    /**
     * Log a visitor entry
     */
    public static function logEntry(
        int $tenantId,
        int $hostelId,
        int $guardId,
        string $guestName,
        string $guestPhone,
        string $decision,
        ?int $preRegistrationId = null,
        ?string $reason = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'hostel_id' => $hostelId,
            'guard_id' => $guardId,
            'guest_name' => $guestName,
            'guest_phone' => $guestPhone,
            'decision' => $decision,
            'pre_registration_id' => $preRegistrationId,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}

