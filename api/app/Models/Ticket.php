<?php

namespace App\Models;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Traits\TenantScoped;

class Ticket extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TenantScoped;

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'location',
        'due_date',
        'tags',
        'reporter_student_id',
        'reporter_user_id',
        'photos',
        'sla_due_at',
        'closed_at',
        'sla_deadline',
        'created_by_user_id',
        'updated_by_user_id',
        'assignee_user_id',
        'created_by',
        'assigned_to',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'tags' => 'array',
        'photos' => 'array',
        'sla_due_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_deadline' => 'datetime',
    ];

    protected $dates = [
        'due_date',
        'sla_due_at',
        'closed_at',
        'sla_deadline',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (!$ticket->created_by && $ticket->created_by_user_id) {
                $ticket->created_by = $ticket->created_by_user_id;
            }

            if (!$ticket->assigned_to && $ticket->assignee_user_id) {
                $ticket->assigned_to = $ticket->assignee_user_id;
            }
        });
    }

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['category', 'title', 'status', 'priority', 'assignee_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the user who created the ticket.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user assigned to the ticket.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->assigneeUser();
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'hostel_id');
    }

    public function reporterStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'reporter_student_id');
    }

    public function reporterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /**
     * Get the comments for the ticket.
     */
    public function comments()
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Scope a query to only include tickets for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include tickets with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include tickets with a specific priority.
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include tickets assigned to a specific user.
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope a query to only include unassigned tickets.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope a query to search tickets by title, description, or location.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('location', 'like', "%{$search}%")
              ->orWhereJsonContains('tags', $search);
        });
    }

    /**
     * Get the ticket's priority color.
     */
    public function getPriorityColorAttribute()
    {
        return match ($this->priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'success',
            default => 'secondary',
        };
    }

    /**
     * Get the ticket's status color.
     */
    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'open' => 'danger',
            'in_progress' => 'warning',
            'resolved' => 'success',
            'closed' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get the ticket's priority label.
     */
    public function getPriorityLabelAttribute()
    {
        return match ($this->priority) {
            'urgent' => 'Urgent',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Unknown',
        };
    }

    /**
     * Get the ticket's status label.
     */
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => 'Unknown',
        };
    }

    /**
     * Check if the ticket is overdue.
     */
    public function isOverdue()
    {
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['resolved', 'closed']);
    }

    /**
     * Check if the ticket is assigned.
     */
    public function isAssigned()
    {
        return !is_null($this->assigned_to);
    }

    /**
     * Get the ticket's age in days.
     */
    public function getAgeInDays()
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get the ticket's time to resolution in days.
     */
    public function getTimeToResolution()
    {
        if (!in_array($this->status, ['resolved', 'closed'])) {
            return null;
        }

        return $this->created_at->diffInDays($this->updated_at);
    }
}
