<?php

namespace App\Domain\SickLeaves\Models;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use Database\Factories\Domain\SickLeaves\SickLeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SickLeave extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return SickLeaveFactory::new();
    }

    protected $fillable = [
        'student_id',
        'hostel_id',
        'unique_id',
        'title',
        'description',
        'illness',
        'illness_details',
        'need_medical_attention',
        'contact_parents',
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
        'need_medical_attention' => 'boolean',
        'contact_parents' => 'boolean',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'sla_warning_sent_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sickLeave) {
            if (empty($sickLeave->unique_id)) {
                $sickLeave->unique_id = 'SLK-' . strtoupper(Str::random(8));
            }
            if (empty($sickLeave->submitted_at)) {
                $sickLeave->submitted_at = now();
            }
            if (empty($sickLeave->sla_due_at) && !empty($sickLeave->submitted_at)) {
                // 4-hour SLA for Sick Leave
                $sickLeave->sla_due_at = $sickLeave->submitted_at->copy()->addHours(4);
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
}

