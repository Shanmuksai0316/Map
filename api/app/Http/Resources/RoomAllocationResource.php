<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RoomAllocation */
class RoomAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'student_id' => (string) $this->student_id,
            'room_bed_id' => (string) $this->room_bed_id,
            'hostel_id' => (string) $this->hostel_id,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'effective_to' => $this->effective_to?->toIso8601String(),
            'note' => $this->note,
            'expected_checkout_at' => $this->expected_checkout_at?->toIso8601String(),
            'checkout_status' => $this->checkout_status,
            'bed' => RoomBedResource::make($this->whenLoaded('roomBed')),
            'student' => StudentResource::make($this->whenLoaded('student')),
            'checkout_checklist' => $this->whenLoaded('checkoutChecklist'),
            'checkout_histories' => $this->whenLoaded('checkoutHistories'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
