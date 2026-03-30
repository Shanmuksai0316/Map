<?php

namespace App\Models;

use App\Enums\SportsEnrollmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportsEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'sports_event_id',
        'student_id',
        'status',
        'enrolled_at',
        'attended_at',
        'waitlist_position',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'attended_at' => 'datetime',
        'metadata' => 'array',
        'status' => SportsEnrollmentStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SportsEvent::class, 'sports_event_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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

    public function canTransitionTo(SportsEnrollmentStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function transitionTo(SportsEnrollmentStatus $newStatus, ?string $notes = null): bool
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

    private function updateStatusTimestamps(SportsEnrollmentStatus $status): void
    {
        $now = now();

        switch ($status) {
            case SportsEnrollmentStatus::ATTENDED:
            case SportsEnrollmentStatus::NO_SHOW:
                $this->attended_at = $now;
                break;
        }
    }

    private function addStatusTransitionNote(SportsEnrollmentStatus $oldStatus, SportsEnrollmentStatus $newStatus, string $notes): void
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

    // Booking and enrollment methods
    public function isRegistered(): bool
    {
        return $this->status === SportsEnrollmentStatus::REGISTERED;
    }

    public function isWaitlisted(): bool
    {
        return $this->status === SportsEnrollmentStatus::WAITLISTED;
    }

    public function isAttended(): bool
    {
        return $this->status === SportsEnrollmentStatus::ATTENDED;
    }

    public function isNoShow(): bool
    {
        return $this->status === SportsEnrollmentStatus::NO_SHOW;
    }

    public function markAsAttended(?string $notes = null): bool
    {
        return $this->transitionTo(SportsEnrollmentStatus::ATTENDED, $notes);
    }

    public function markAsNoShow(?string $notes = null): bool
    {
        return $this->transitionTo(SportsEnrollmentStatus::NO_SHOW, $notes);
    }

    public function cancel(?string $notes = null): bool
    {
        return $this->transitionTo(SportsEnrollmentStatus::CANCELLED, $notes);
    }

    public function getStatusHistory(): array
    {
        return $this->metadata['status_transitions'] ?? [];
    }

    public function getWaitlistPosition(): ?int
    {
        if (!$this->isWaitlisted()) {
            return null;
        }

        return $this->waitlist_position ?? $this->event
            ->waitlistedEnrollments()
            ->where('enrolled_at', '<=', $this->enrolled_at)
            ->count();
    }
}
