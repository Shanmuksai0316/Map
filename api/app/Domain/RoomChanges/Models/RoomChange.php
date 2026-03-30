<?php

namespace App\Domain\RoomChanges\Models;

use App\Models\Hostel;
use App\Models\RoomAllocation;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Traits\TenantScoped;
use Database\Factories\Domain\RoomChanges\RoomChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RoomChange extends Model
{
    use HasFactory;
    use TenantScoped;

    protected static function newFactory()
    {
        return RoomChangeFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'student_id',
        'hostel_id',
        'unique_id',
        'title',
        'description',
        'preferred_room_number',
        'preferred_floor',
        'sharing_preference',
        'date_required',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'submitted_at',
        'idempotency_key',
        'sla_due_at',
        'last_reminded_at',
        'last_escalated_at',
    ];

    protected $casts = [
        'date_required' => 'date',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'last_escalated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($roomChange) {
            if (empty($roomChange->unique_id)) {
                $roomChange->unique_id = 'RMC-' . strtoupper(Str::random(8));
            }
            if (empty($roomChange->title)) {
                $roomChange->title = 'Room Change Request';
            }
            if (empty($roomChange->submitted_at)) {
                $roomChange->submitted_at = now();
            }
            if (empty($roomChange->sla_due_at)) {
                $base = $roomChange->submitted_at
                    ? Carbon::parse($roomChange->submitted_at)
                    : now();

                $roomChange->sla_due_at = $base->copy()->addHours(
                    config('reminders.room_changes.sla_hours', 24)
                );
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function latestAllocation(): BelongsTo
    {
        return $this->belongsTo(RoomAllocation::class, 'student_id', 'student_id')
            ->where('is_active', true);
    }
}

