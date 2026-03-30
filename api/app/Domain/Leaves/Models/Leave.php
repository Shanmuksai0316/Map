<?php

namespace App\Domain\Leaves\Models;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use Database\Factories\Domain\Leaves\LeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Leave extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return LeaveFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'student_id',
        'hostel_id',
        'unique_id',
        'title',
        'description',
        'reason_for_leave',
        'from_date',
        'to_date',
        'emergency_contact',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'submitted_at',
        'idempotency_key',
        'sla_due_at',
        'sla_breached_at',
        'sla_warning_sent_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'sla_warning_sent_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($leave) {
            if (empty($leave->unique_id)) {
                $leave->unique_id = 'LEV-' . strtoupper(Str::random(8));
            }
            if (empty($leave->submitted_at)) {
                $leave->submitted_at = now();
            }
            if (empty($leave->sla_due_at) && !empty($leave->submitted_at)) {
                // 4-hour SLA for Leave
                $leave->sla_due_at = $leave->submitted_at->copy()->addHours(4);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(\App\Domain\Leaves\Models\LeaveHistory::class);
    }

    public function recordHistory(?string $from, string $to, ?string $note = null, ?int $actorId = null, ?string $label = null, ?string $description = null): void
    {
        $this->histories()->create([
            'tenant_id' => $this->tenant_id,
            'acted_by' => $actorId ?? Auth::id(),
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'timeline_label' => $label,
            'timeline_description' => $description,
            'changed_at' => now(),
        ]);
    }
}

