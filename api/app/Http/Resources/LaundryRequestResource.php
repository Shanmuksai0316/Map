<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaundryRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->getLabel(),
                'description' => $this->status->getDescription(),
                'color' => $this->status->getColor(),
                'is_active' => $this->status->isActive(),
                'is_in_progress' => $this->status->isInProgress(),
                'is_completed' => $this->status->isCompleted(),
                'is_failed' => $this->status->isFailed(),
            ],
            'service_type' => [
                'value' => $this->service_type->value,
                'label' => $this->service_type->getLabel(),
                'description' => $this->service_type->getDescription(),
                'price_multiplier' => $this->service_type->getPriceMultiplier(),
                'estimated_duration_hours' => $this->service_type->getEstimatedDuration(),
                'requires_ironing' => $this->service_type->requiresIroning(),
                'is_express' => $this->service_type->isExpress(),
                'is_dry_clean' => $this->service_type->isDryClean(),
            ],
            'bag_count' => $this->bag_count,
            'weight_kg' => $this->weight_kg,
            'special_instructions' => $this->special_instructions,
            'collection_notes' => $this->collection_notes,
            'delivery_notes' => $this->delivery_notes,
            'manual_verify_notes' => $this->manual_verify_notes,
            'requested_at' => $this->requested_at?->toISOString(),
            'ready_at' => $this->ready_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'estimated_completion_at' => $this->estimated_completion_at?->toISOString(),
            'actual_completion_at' => $this->actual_completion_at?->toISOString(),
            'price' => [
                'base_price' => 50,
                'multiplier' => $this->service_type->getPriceMultiplier(),
                'total' => $this->calculatePrice(),
                'currency' => 'INR',
            ],
            'payment' => [
                'status' => $this->payment_status,
                'amount' => $this->payment_amount,
                'method' => $this->payment_method,
                'reference' => $this->payment_reference,
                'requires_payment' => $this->requiresPayment(),
            ],
            'verification' => [
                'requires_manual_verify' => $this->requiresManualVerify(),
                'manual_verify_notes' => $this->manual_verify_notes,
            ],
            'overdue' => [
                'is_overdue' => $this->isOverdue(),
                'days_overdue' => $this->getDaysOverdue(),
            ],
            'status_history' => $this->getStatusHistory(),
            'student' => new StudentResource($this->whenLoaded('student')),
            'cycle' => new LaundryCycleResource($this->whenLoaded('cycle')),
            'hostel' => new HostelResource($this->whenLoaded('hostel')),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}