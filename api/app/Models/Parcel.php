<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parcel extends Model
{
    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'student_id',
        'received_by_user_id',
        'status',
        'code',
        'room_number',
        'notes',
        'informed_at',
        'received_at',
        'received_verified_by_user_id',
        'code_expires_at',
        'code_attempts',
        'code_last_attempt_at',
    ];

    protected $casts = [
        'informed_at' => 'datetime',
        'received_at' => 'datetime',
        'code_expires_at' => 'datetime',
        'code_last_attempt_at' => 'datetime',
    ];

    public const STATUS_INFORMED = 'informed';
    public const STATUS_RECEIVED = 'received';
    public const CODE_TTL_HOURS = 72;
    public const CODE_MAX_ATTEMPTS = 5;
    public const CODE_ATTEMPT_WINDOW_MINUTES = 60;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function receivedVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_verified_by_user_id');
    }

    public function isPendingReceive(): bool
    {
        return $this->status === self::STATUS_INFORMED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function verifyCode(string $inputCode): bool
    {
        return $inputCode !== '' && $this->code === $inputCode;
    }

    public function isCodeExpired(): bool
    {
        return $this->code_expires_at !== null && $this->code_expires_at->isPast();
    }

    public function isRateLimited(): bool
    {
        if ($this->code_attempts < self::CODE_MAX_ATTEMPTS) {
            return false;
        }

        if (! $this->code_last_attempt_at) {
            return false;
        }

        return $this->code_last_attempt_at->greaterThanOrEqualTo(
            now()->subMinutes(self::CODE_ATTEMPT_WINDOW_MINUTES)
        );
    }
}
