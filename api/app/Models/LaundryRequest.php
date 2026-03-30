<?php

namespace App\Models;

use App\Enums\LaundryRequestStatus;
use App\Enums\LaundryServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LaundryRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
        'campus_id',
        'hostel_id',
        'student_id',
        'initiated_by_user_id',
        'laundry_cycle_id',
        'service_type',
        'status',
        'bag_count',
        'total_clothes',
        'weight_kg',
        'requested_at',
        'ready_at',
        'completed_at',
        'special_instructions',
        'notes',
        'metadata',
        'estimated_completion_at',
        'actual_completion_at',
        'collection_notes',
        'delivery_notes',
        'manual_verify_notes',
        'payment_status',
        'payment_amount',
        'payment_method',
        'payment_reference',
        'pickup_code',
        'delayed_notified_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_completion_at' => 'datetime',
        'actual_completion_at' => 'datetime',
        'weight_kg' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'metadata' => 'array',
        'status' => LaundryRequestStatus::class,
        'service_type' => LaundryServiceType::class,
        'delayed_notified_at' => 'datetime',
    ];

    /** Soft SLA: max hours for laundry request completion. */
    public const SLA_HOURS = 72;

    /**
     * Get the tenant this model belongs to.
     * With database-per-tenant, tenant context is automatic.
     */
    public function tenant(): ?\App\Models\Tenant
    {
        return tenancy()->tenant;
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(LaundryCycle::class, 'laundry_cycle_id');
    }

    /**
     * Get the user who initiated this laundry request (Laundry Manager)
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * Check if request was initiated by Laundry Manager
     */
    public function isManagerInitiated(): bool
    {
        return $this->initiated_by_user_id !== null;
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

    /**
     * Whether this laundry request is delayed (soft SLA: exceeded max hours and not completed).
     * Delayed is an additional tag for display; status is unchanged.
     */
    public function isDelayed(): bool
    {
        if ($this->isCompleted() || $this->status->isFailed()) {
            return false;
        }
        $hours = (int) config('requests.sla_hours', self::SLA_HOURS);
        $from = $this->requested_at ?? $this->created_at;
        if (! $from) {
            return false;
        }
        $deadline = $from->copy()->addHours($hours);

        return now()->isAfter($deadline);
    }

    /**
     * Scope: laundry requests that are delayed (not completed and requested more than SLA hours ago).
     */
    public function scopeDelayed($query)
    {
        $hours = (int) config('requests.sla_hours', self::SLA_HOURS);
        $cutoff = now()->subHours($hours);

        return $query
            ->whereNotIn('status', ['delivered', 'completed', 'cancelled', 'lost', 'damaged'])
            ->where(function ($q) use ($cutoff) {
                $q->where('requested_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff) {
                        $q2->whereNull('requested_at')->where('created_at', '<', $cutoff);
                    });
            });
    }

    /**
     * Scope: delayed laundry requests that have not yet triggered a campus manager notification.
     */
    public function scopeDelayedUnnotified($query)
    {
        return $query->delayed()->whereNull('delayed_notified_at');
    }

    public function canTransitionTo(LaundryRequestStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function transitionTo(LaundryRequestStatus $newStatus, ?string $notes = null): bool
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

    private function updateStatusTimestamps(LaundryRequestStatus $status): void
    {
        $now = now();

        switch ($status) {
            case LaundryRequestStatus::SCHEDULED:
                $this->requested_at = $this->requested_at ?? $now;
                break;
            case LaundryRequestStatus::COLLECTED:
                $this->requested_at = $this->requested_at ?? $now;
                break;
            case LaundryRequestStatus::READY:
                $this->ready_at = $now;
                // Generate 4-digit pickup code if not already set
                if (!$this->pickup_code) {
                    $this->pickup_code = sprintf('%04d', mt_rand(0, 9999));
                }
                // Send notification to student
                $this->sendReadyForPickupNotification();
                break;
            case LaundryRequestStatus::COMPLETED:
            case LaundryRequestStatus::DELIVERED:
                $this->completed_at = $now;
                $this->actual_completion_at = $now;
                break;
        }
    }

    private function addStatusTransitionNote(LaundryRequestStatus $oldStatus, LaundryRequestStatus $newStatus, string $notes): void
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

        $baseTime = $this->service_type->getEstimatedDuration();
        $startTime = $this->requested_at ?? now();

        return $startTime->addHours($baseTime);
    }

    public function getStatusHistory(): array
    {
        return $this->metadata['status_transitions'] ?? [];
    }

    public function getServicePrice(): float
    {
        $basePrice = 50; // Base price per bag
        $multiplier = $this->service_type->getPriceMultiplier();
        
        return $basePrice * $this->bag_count * $multiplier;
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
        return $estimatedCompletion->diffInDays(now());
    }

    /**
     * Check if manual verification is required
     */
    public function requiresManualVerify(): bool
    {
        return in_array($this->status, [LaundryRequestStatus::READY]);
    }

    /**
     * Perform manual verification
     */
    public function manualVerify(string $notes): bool
    {
        if (!$this->requiresManualVerify()) {
            return false;
        }

        $this->manual_verify_notes = $notes;
        $this->transitionTo(LaundryRequestStatus::DELIVERED, $notes);

        return $this->save();
    }

    /**
     * Check if payment is required
     */
    public function requiresPayment(): bool
    {
        return $this->payment_status === 'pending' || $this->payment_status === null;
    }

    /**
     * Mark payment as completed
     */
    public function markPaymentCompleted(string $method, string $reference, float $amount): bool
    {
        $this->payment_status = 'completed';
        $this->payment_method = $method;
        $this->payment_reference = $reference;
        $this->payment_amount = $amount;

        return $this->save();
    }

    /**
     * Calculate service price based on type, bag count, and weight
     */
    public function calculatePrice(): float
    {
        $basePrice = 50; // Base price per bag
        $serviceMultiplier = $this->service_type->getPriceMultiplier();
        $weightMultiplier = 1; // Default multiplier

        if ($this->weight_kg && $this->weight_kg > 0) {
            $weightMultiplier = $this->weight_kg / 5; // 5kg is standard weight
        }

        return $basePrice * $this->bag_count * $serviceMultiplier * $weightMultiplier;
    }

    /**
     * Send notification to student when laundry is ready for pickup
     */
    private function sendReadyForPickupNotification(): void
    {
        try {
            $student = $this->student;
            if (!$student || !$student->user) {
                \Log::warning('Cannot send ready notification - student or user not found', [
                    'laundry_request_id' => $this->id,
                ]);
                return;
            }

            $studentUser = $student->user;
            $title = 'Laundry Ready for Pickup';
            $message = "Your laundry request #{$this->id} is ready for pickup. Pickup code: {$this->pickup_code}";

            // Send push notification
            $pushNotifier = app(\App\Services\Notify\PushNotifier::class);
            $pushNotifier->toUser(
                $studentUser->id,
                $title,
                $message,
                [
                    'type' => 'laundry_ready',
                    'laundry_request_id' => $this->id,
                    'pickup_code' => $this->pickup_code,
                ]
            );

            \Log::info('Laundry ready notification sent', [
                'laundry_request_id' => $this->id,
                'student_user_id' => $studentUser->id,
                'pickup_code' => $this->pickup_code,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send laundry ready notification', [
                'laundry_request_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
