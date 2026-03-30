<?php

namespace App\Domain\Visitors\Models;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Traits\TenantScoped;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GuestVisit extends Model
{
    use HasFactory, TenantScoped;

    public const STATUS_PRE_REGISTERED = 'pre_registered';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ALLOWED = 'allowed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'student_id',
        'name',
        'phone',
        'relation',
        'id_proof_type',
        'id_proof_number',
        'whom_to_meet',
        'visit_date',
        'entry_time',
        'exit_time',
        'description',
        'status',
        'created_by_user_id',
        'allowed_by_user_id',
        'allowed_at',
        'denied_by_user_id',
        'denied_at',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'allowed_at' => 'datetime',
        'denied_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function allowedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allowed_by_user_id');
    }

    public function deniedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'denied_by_user_id');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForHostelDate(Builder $query, int $hostelId, Carbon $date): Builder
    {
        return $query->where('hostel_id', $hostelId)
            ->whereDate('visit_date', $date);
    }

    public function scopeMine(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }
}

