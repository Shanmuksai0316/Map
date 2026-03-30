<?php

namespace App\Domain\Gate\Models;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Domain\OutPass\OutPass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GateEntry extends Model
{
    use HasFactory;

    public const DIRECTION_OUT = 'out';
    public const DIRECTION_IN = 'in';

    public const METHOD_QR = 'qr';
    public const METHOD_OTP = 'otp';
    public const METHOD_MANUAL = 'manual';

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'hostel_id',
        'guard_id',
        'student_id',
        'outpass_id',
        'event',
        'occurred_at',
        'source',
        'was_offline',
        'synced_at',
        'notes',
        'metadata',
        'client_reference',
        'direction',
        'method',
        'verified',
        'verified_at',
        'guard_user_id',
        'note',
        'late_minutes',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'late_minutes' => 'integer',
        'occurred_at' => 'datetime',
        'synced_at' => 'datetime',
        'was_offline' => 'boolean',
        'metadata' => 'array',
    ];

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

    public function outpass(): BelongsTo
    {
        return $this->belongsTo(OutPass::class);
    }

    public function guardUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_user_id');
    }

    public function isLate(): bool
    {
        return $this->late_minutes > 0;
    }

    public function scopeForTodayHostel(Builder $query, int $hostelId): Builder
    {
        return $query->where('hostel_id', $hostelId)
            ->whereDate('created_at', today());
    }
}

