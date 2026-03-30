<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Visitor extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'visitor_name',
        'visitor_phone',
        'visitor_id_type',
        'visitor_id_number',
        'student_id',
        'guard_id',
        'purpose',
        'expected_duration',
        'vehicle_number',
        'accompanying_persons',
        'notes',
        'status',
        'visit_date',
        'allowed_at',
        'allowed_by',
        'denied_at',
        'denied_by',
        'denial_reason',
        'exited_at',
        'exited_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'allowed_at' => 'datetime',
        'denied_at' => 'datetime',
        'exited_at' => 'datetime',
        'expected_duration' => 'integer',
        'accompanying_persons' => 'integer',
    ];

    protected $dates = [
        'visit_date',
        'allowed_at',
        'denied_at',
        'exited_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['visitor_name', 'status', 'purpose'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the student that the visitor is visiting.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the guard who registered the visitor.
     */
    public function guard()
    {
        return $this->belongsTo(User::class, 'guard_id');
    }

    /**
     * Get the user who allowed the visitor.
     */
    public function allowedBy()
    {
        return $this->belongsTo(User::class, 'allowed_by');
    }

    /**
     * Get the user who denied the visitor.
     */
    public function deniedBy()
    {
        return $this->belongsTo(User::class, 'denied_by');
    }

    /**
     * Get the user who recorded the visitor exit.
     */
    public function exitedBy()
    {
        return $this->belongsTo(User::class, 'exited_by');
    }

    /**
     * Scope a query to only include visitors for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include visitors with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include visitors for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('visit_date', $date);
    }

    /**
     * Scope a query to only include visitors for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('visit_date', today());
    }

    /**
     * Scope a query to only include visitors within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('visit_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to search visitors by name, phone, or purpose.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('visitor_name', 'like', "%{$search}%")
              ->orWhere('visitor_phone', 'like', "%{$search}%")
              ->orWhere('purpose', 'like', "%{$search}%")
              ->orWhereHas('student', function ($studentQuery) use ($search) {
                  $studentQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('roll_no', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Get the visitor's status badge color.
     */
    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'pending' => 'warning',
            'allowed' => 'success',
            'denied' => 'danger',
            'exited' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get the visitor's status label.
     */
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'pending' => 'Pending Approval',
            'allowed' => 'Allowed Entry',
            'denied' => 'Entry Denied',
            'exited' => 'Exited Campus',
            default => 'Unknown',
        };
    }

    /**
     * Check if the visitor is still on campus.
     */
    public function isOnCampus()
    {
        return $this->status === 'allowed' && !$this->exited_at;
    }

    /**
     * Check if the visitor's visit has expired.
     */
    public function isExpired()
    {
        if (!$this->expected_duration || !$this->allowed_at) {
            return false;
        }

        $expiryTime = $this->allowed_at->addMinutes($this->expected_duration);
        return now()->isAfter($expiryTime);
    }

    /**
     * Get the visitor's duration on campus.
     */
    public function getDurationOnCampus()
    {
        if (!$this->allowed_at) {
            return null;
        }

        $endTime = $this->exited_at ?? now();
        return $this->allowed_at->diffInMinutes($endTime);
    }
}

