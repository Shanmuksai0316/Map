<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaundryCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'machine_label' => $this->machine_label,
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
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'estimated_completion_at' => $this->estimated_completion_at?->toISOString(),
            'actual_completion_at' => $this->actual_completion_at?->toISOString(),
            'cycle_notes' => $this->cycle_notes,
            'overdue' => [
                'is_overdue' => $this->isOverdue(),
                'days_overdue' => $this->getDaysOverdue(),
            ],
            'metrics' => [
                'total_requests' => $this->getTotalRequests(),
                'active_requests' => $this->getActiveRequests(),
                'completed_requests' => $this->getCompletedRequests(),
                'total_bags' => $this->getTotalBags(),
                'efficiency_score' => $this->getEfficiencyScore(),
            ],
            'status_history' => $this->getStatusHistory(),
            'operator' => new UserResource($this->whenLoaded('operator')),
            'hostel' => new HostelResource($this->whenLoaded('hostel')),
            'requests' => LaundryRequestResource::collection($this->whenLoaded('requests')),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}