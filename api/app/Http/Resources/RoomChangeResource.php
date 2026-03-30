<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomChangeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'unique_id' => $this->unique_id,
            'status' => $this->status,
            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->user?->name,
                'phone' => $this->student?->user?->phone,
            ],
            'hostel' => [
                'id' => $this->hostel?->id,
                'name' => $this->hostel?->name,
            ],
            'preferred_room_number' => $this->preferred_room_number,
            'preferred_floor' => $this->preferred_floor,
            'sharing_preference' => $this->sharing_preference,
            'date_required' => optional($this->date_required)->toDateString(),
            'description' => $this->description,
            'rejection_reason' => $this->rejection_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
