<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_at' => $this->start_at->toISOString(),
            'end_at' => $this->end_at->toISOString(),
            'status' => $this->status,
            'purpose' => $this->purpose,
            'participants' => $this->participants,
            'notes' => $this->notes,
            'can_cancel' => $this->canCancel(),
            'is_upcoming' => $this->isUpcoming(),
            'is_active' => $this->isActive(),
            'has_ended' => $this->hasEnded(),
            'facility' => $this->whenLoaded('facility', [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'type' => $this->facility->type,
                'hostel' => $this->whenLoaded('facility.hostel', [
                    'id' => $this->facility->hostel->id,
                    'name' => $this->facility->hostel->name,
                ]),
            ]),
            'student' => $this->whenLoaded('student', [
                'id' => $this->student->id,
                'map_student_id' => $this->student->map_student_id,
                'name' => $this->student->user->name ?? 'Unknown',
                'room' => $this->student->room ? [
                    'id' => $this->student->room->id,
                    'number' => $this->student->room->number,
                    'hostel' => $this->student->room->hostel->name ?? null,
                ] : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
