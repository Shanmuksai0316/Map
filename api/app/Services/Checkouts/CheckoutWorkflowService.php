<?php

namespace App\Services\Checkouts;

use App\Models\ActivityFeedEntry;
use App\Models\CheckoutChecklist;
use App\Models\CheckoutHistory;
use App\Models\RoomAllocation;
use App\Services\Students\StudentLifecycleService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutWorkflowService
{
    public function __construct(private readonly StudentLifecycleService $lifecycleService) {}

    public function start(RoomAllocation $allocation, array $data = []): CheckoutChecklist
    {
        if ($allocation->checkout_status === 'completed') {
            throw ValidationException::withMessages([
                'room_allocation' => 'Checkout already completed.',
            ]);
        }

        $checklist = $allocation->checkoutChecklist()->firstOrNew([]);

        $checklist->fill([
            'tenant_id' => $allocation->tenant_id,
            'status' => 'in_progress',
            'inspection_passed' => Arr::get($data, 'inspection_passed'),
            'keys_collected' => Arr::get($data, 'keys_collected'),
            'dues_cleared' => Arr::get($data, 'dues_cleared'),
            'notes' => Arr::get($data, 'notes'),
            'photos' => Arr::get($data, 'photos', []),
            'created_by' => auth()->id(),
        ]);

        DB::transaction(function () use ($checklist, $allocation): void {
            $checklist->save();

            $allocation->forceFill([
                'checkout_status' => 'in_progress',
            ])->save();

            $this->recordHistory($allocation, 'started');
        });

        return $checklist->fresh();
    }

    public function complete(RoomAllocation $allocation, array $data): CheckoutChecklist
    {
        if ($allocation->checkout_status === 'pending') {
            throw ValidationException::withMessages([
                'room_allocation' => 'Checkout needs to be started before completion.',
            ]);
        }

        return DB::transaction(function () use ($allocation, $data): CheckoutChecklist {
            $checklist = $allocation->checkoutChecklist()->firstOrFail();

            $checklist->fill([
                'inspection_passed' => Arr::get($data, 'inspection_passed', true),
                'keys_collected' => Arr::get($data, 'keys_collected', true),
                'dues_cleared' => Arr::get($data, 'dues_cleared', true),
                'notes' => Arr::get($data, 'notes'),
                'photos' => Arr::get($data, 'photos', []),
                'status' => 'completed',
                'completed_by' => auth()->id(),
                'completed_at' => now(),
            ])->save();

            $allocation->forceFill([
                'checkout_status' => 'completed',
                'is_active' => false,
                'effective_to' => $allocation->effective_to ?? now(),
            ])->save();

            $this->recordHistory($allocation, 'completed', [
                'completed_by' => auth()->id(),
            ]);

            $this->lifecycleService->archive($allocation->student, Carbon::now(), 'Checkout completed');

            ActivityFeedEntry::create([
                'tenant_id' => $allocation->tenant_id,
                'type' => 'checkout.completed',
                'channel' => 'system',
                'related_type' => RoomAllocation::class,
                'related_id' => (string) $allocation->id,
                'title' => 'Checkout completed',
                'body' => $allocation->student?->user?->name,
                'metadata' => [
                    'room_allocation_id' => $allocation->id,
                ],
                'created_by' => auth()->id(),
            ]);

            return $checklist;
        });
    }

    /**
     * Extend expected checkout date by the configured period (default 1.5 years).
     * Allowed when checkout_status is pending or in_progress (not completed).
     */
    public function extend(RoomAllocation $allocation): RoomAllocation
    {
        if ($allocation->checkout_status === 'completed') {
            throw ValidationException::withMessages([
                'room_allocation' => 'Cannot extend; checkout already completed.',
            ]);
        }

        if (! $allocation->is_active) {
            throw ValidationException::withMessages([
                'room_allocation' => 'Cannot extend; allocation is not active.',
            ]);
        }

        // Tenant-specific renewal period takes precedence over config default
        $tenant = $allocation->tenant;
        $periodMonths = (int) ($tenant?->settings['renewal_period_months'] ?? config('checkouts.default_period_months', 12));
        $previousExpected = $allocation->expected_checkout_at
            ? Carbon::parse($allocation->expected_checkout_at)
            : Carbon::parse($allocation->effective_from)->addMonths($periodMonths);
        $newExpected = $previousExpected->copy()->addMonths($periodMonths);

        DB::transaction(function () use ($allocation, $previousExpected, $newExpected): void {
            $allocation->forceFill([
                'expected_checkout_at' => $newExpected,
                'checkout_notified_at' => null,
            ])->save();

            $this->recordHistory($allocation, 'extended', [
                'previous_expected_checkout_at' => $previousExpected->toIso8601String(),
                'new_expected_checkout_at' => $newExpected->toIso8601String(),
                'extended_by' => auth()->id(),
            ]);
        });

        return $allocation->fresh();
    }

    public function recordHistory(RoomAllocation $allocation, string $event, array $payload = []): void
    {
        CheckoutHistory::create([
            'tenant_id' => $allocation->tenant_id,
            'room_allocation_id' => $allocation->id,
            'event' => $event,
            'payload' => $payload,
            'created_by' => auth()->id(),
        ]);
    }
}
