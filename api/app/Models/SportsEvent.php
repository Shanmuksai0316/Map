<?php

namespace App\Models;

use App\Enums\SportsEventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SportsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'campus_id',
        'hostel_id',
        'sport',
        'name',
        'description',
        'scheduled_at',
        'end_time',
        'venue',
        'status',
        'capacity',
        'registration_deadline',
        'requirements',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'end_time' => 'datetime',
        'registration_deadline' => 'datetime',
        'metadata' => 'array',
        'status' => SportsEventStatus::class,
    ];

    /**
     * Tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SportsEnrollment::class, 'sports_event_id');
    }

    public function registeredEnrollments(): HasMany
    {
        return $this->hasMany(SportsEnrollment::class, 'sports_event_id')
            ->where('status', 'registered');
    }

    public function waitlistedEnrollments(): HasMany
    {
        return $this->hasMany(SportsEnrollment::class, 'sports_event_id')
            ->where('status', 'waitlisted');
    }

    // Lifecycle methods
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function canTransitionTo(SportsEventStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function transitionTo(SportsEventStatus $newStatus, ?string $notes = null): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            return false;
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;

        // Update timestamps based on status
        $this->updateStatusTimestamps($newStatus);

        // Add transition notes
        if ($notes) {
            $this->addStatusTransitionNote($oldStatus, $newStatus, $notes);
        }

        return $this->save();
    }

    private function updateStatusTimestamps(SportsEventStatus $status): void
    {
        $now = now();

        switch ($status) {
            case SportsEventStatus::ONGOING:
                $this->scheduled_at = $this->scheduled_at ?? $now;
                break;
            case SportsEventStatus::COMPLETED:
                $this->end_time = $this->end_time ?? $now;
                break;
        }
    }

    private function addStatusTransitionNote(SportsEventStatus $oldStatus, SportsEventStatus $newStatus, string $notes): void
    {
        $transitions = $this->metadata['status_transitions'] ?? [];
        $transitions[] = [
            'from' => $oldStatus->value,
            'to' => $newStatus->value,
            'timestamp' => now()->toISOString(),
            'notes' => $notes,
            'user_id' => auth()->id(),
        ];

        $this->metadata = array_merge($this->metadata ?? [], [
            'status_transitions' => $transitions,
        ]);
    }

    // Booking and capacity methods
    public function getAvailableSpots(): int
    {
        $registeredCount = $this->enrollments()->where('status', 'registered')->count();
        return max(0, $this->capacity - $registeredCount);
    }

    public function isFull(): bool
    {
        return $this->getAvailableSpots() <= 0;
    }

    public function hasCapacity(): bool
    {
        return $this->getAvailableSpots() > 0;
    }

    public function canEnroll(): bool
    {
        return $this->isActive() && 
               $this->hasCapacity() && 
               (!$this->registration_deadline || now()->isBefore($this->registration_deadline));
    }

    public function isRegistrationOpen(): bool
    {
        return !$this->registration_deadline || now()->isBefore($this->registration_deadline);
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isFuture();
    }

    public function isPast(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isPast();
    }

    public function getDuration(): ?int
    {
        if ($this->scheduled_at && $this->end_time) {
            return $this->scheduled_at->diffInMinutes($this->end_time);
        }
        return null;
    }

    public function getStatusHistory(): array
    {
        return $this->metadata['status_transitions'] ?? [];
    }
}
