<?php

namespace App\Models;

use App\Enums\LaundryCycleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LaundryCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'machine_label',
        'status',
        'started_at',
        'completed_at',
        'metadata',
        'estimated_completion_at',
        'actual_completion_at',
        'cycle_notes',
        'operator_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_completion_at' => 'datetime',
        'actual_completion_at' => 'datetime',
        'metadata' => 'array',
        'status' => LaundryCycleStatus::class,
    ];

    /**
     * Tenant relationship.
     *
     * This must return an actual Eloquent relationship instance.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LaundryRequest::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    // Lifecycle methods
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function canTransitionTo(LaundryCycleStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function transitionTo(LaundryCycleStatus $newStatus, ?string $notes = null): bool
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

    private function updateStatusTimestamps(LaundryCycleStatus $status): void
    {
        $now = now();

        switch ($status) {
            case LaundryCycleStatus::IN_PROGRESS:
                $this->started_at = $this->started_at ?? $now;
                break;
            case LaundryCycleStatus::COMPLETED:
                $this->completed_at = $now;
                $this->actual_completion_at = $now;
                break;
        }
    }

    private function addStatusTransitionNote(LaundryCycleStatus $oldStatus, LaundryCycleStatus $newStatus, string $notes): void
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

    public function calculateEstimatedCompletion(): Carbon
    {
        if ($this->estimated_completion_at) {
            return $this->estimated_completion_at;
        }

        // Estimate based on number of requests and their service types
        $totalHours = $this->requests()->get()->sum(function ($request) {
            return $request->service_type->getEstimatedDuration();
        });

        $startTime = $this->started_at ?? now();
        return $startTime->addHours($totalHours);
    }

    public function getStatusHistory(): array
    {
        return $this->metadata['status_transitions'] ?? [];
    }

    public function getTotalRequests(): int
    {
        return $this->requests()->count();
    }

    public function getActiveRequests(): int
    {
        return $this->requests()->whereIn('status', [
            LaundryRequestStatus::SCHEDULED,
            LaundryRequestStatus::COLLECTED,
            LaundryRequestStatus::WASHING,
            LaundryRequestStatus::DRYING,
            LaundryRequestStatus::READY,
        ])->count();
    }

    public function getCompletedRequests(): int
    {
        return $this->requests()->whereIn('status', [
            LaundryRequestStatus::COMPLETED,
            LaundryRequestStatus::DELIVERED,
        ])->count();
    }

    public function getTotalBags(): int
    {
        return $this->requests()->sum('bag_count');
    }

    public function isOverdue(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $estimatedCompletion = $this->calculateEstimatedCompletion();
        return now()->isAfter($estimatedCompletion);
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $estimatedCompletion = $this->calculateEstimatedCompletion();
        return now()->diffInDays($estimatedCompletion);
    }

    public function getEfficiencyScore(): float
    {
        $totalRequests = $this->getTotalRequests();
        $completedRequests = $this->getCompletedRequests();

        if ($totalRequests === 0) {
            return 0.0;
        }

        return ($completedRequests / $totalRequests) * 100;
    }
}
