<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Tickets\Models\TicketComment;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',      // Required for RLS in shared database
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
        'delayed_notified_at',
        'created_by_user_id',
        'updated_by_user_id',
        'assignee_user_id',
        'created_by',
        'assigned_to',
    ];

    protected $casts = [
        'tags' => 'array',
        'due_date' => 'datetime',
        'photos' => 'array',
        'sla_due_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'delayed_notified_at' => 'datetime',
    ];

    /** Soft SLA: max hours for request completion (Housekeeping, Repair & Maintenance). */
    public const SLA_HOURS = 72;

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

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }

    // Helper methods
    public function canTransitionTo(string $nextStatus): bool
    {
        $allowedTransitions = [
            'open' => ['in_progress', 'on_hold', 'closed'],
            'in_progress' => ['on_hold', 'resolved', 'closed'],
            'on_hold' => ['in_progress', 'closed'],
            'resolved' => ['closed', 'open'],
            'closed' => ['open'],
        ];

        return in_array($nextStatus, $allowedTransitions[$this->status] ?? []);
    }

    public function getAllowedTransitions(): array
    {
        $allowedTransitions = [
            'open' => ['in_progress', 'on_hold', 'closed'],
            'in_progress' => ['on_hold', 'resolved', 'closed'],
            'on_hold' => ['in_progress', 'closed'],
            'resolved' => ['closed', 'open'],
            'closed' => ['open'],
        ];

        return $allowedTransitions[$this->status] ?? [];
    }

    public function isWithinSla(): bool
    {
        if (! $this->due_date) {
            return true;
        }

        return now()->lte($this->due_date);
    }

    public function isBreached(): bool
    {
        return ! $this->isWithinSla();
    }

    public function getReporterNameAttribute(): string
    {
        // Use created_by relationship since reporter fields don't exist in migration
        return $this->reporterUser?->name
            ?? $this->reporterStudent?->user?->name
            ?? 'Unknown';
    }

    public function getReporterTypeAttribute(): string
    {
        // Use created_by relationship since reporter fields don't exist in migration
        if ($this->reporterUser) {
            return 'staff';
        }

        if ($this->reporterStudent) {
            return 'student';
        }

        return 'unknown';
    }

    // Scopes
    public function scopeMine($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('created_by_user_id', $user->id)
                ->orWhere('assignee_user_id', $user->id);
        });
    }

    public function scopeInHostels($query, array $hostelIds)
    {
        return $query->whereIn('hostel_id', $hostelIds);
    }

    public function scopeByStatus($query, array $statuses)
    {
        return $query->whereIn('status', $statuses);
    }

    public function scopeByCategory($query, array $categories)
    {
        return $query->whereIn('category', $categories);
    }

    public function scopeByPriority($query, array $priorities)
    {
        return $query->whereIn('priority', $priorities);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where('title', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    }

    public function scopeWithinSla($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('due_date')
                ->orWhere('due_date', '>=', now());
        });
    }

    public function scopeBreached($query)
    {
        return $query->where('due_date', '<', now());
    }

    /**
     * Whether this request is delayed (soft SLA: exceeded max hours and not resolved/closed).
     * Delayed is an additional tag for display; status is unchanged.
     */
    public function isDelayed(): bool
    {
        if (in_array($this->status, ['resolved', 'closed'], true)) {
            return false;
        }
        $hours = (int) config('requests.sla_hours', self::SLA_HOURS);
        $deadline = $this->created_at->copy()->addHours($hours);

        return now()->isAfter($deadline);
    }

    /**
     * Scope: requests that are delayed (open/in_progress/on_hold and created more than SLA hours ago).
     */
    public function scopeDelayed($query)
    {
        $hours = (int) config('requests.sla_hours', self::SLA_HOURS);
        $cutoff = now()->subHours($hours);

        return $query
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where('created_at', '<', $cutoff);
    }

    /**
     * Scope: delayed requests that have not yet triggered a campus manager notification.
     */
    public function scopeDelayedUnnotified($query)
    {
        return $query->delayed()->whereNull('delayed_notified_at');
    }
}
