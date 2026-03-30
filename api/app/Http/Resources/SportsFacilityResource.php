<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportsFacilityResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type,
            'open_time' => $this->open_time?->format('H:i'),
            'close_time' => $this->close_time?->format('H:i'),
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'rules' => $this->rules,
            'hostel' => $this->whenLoaded('hostel', [
                'id' => $this->hostel->id,
                'name' => $this->hostel->name,
                'code' => $this->hostel->code,
            ]),
            'stats' => $this->when($request->routeIs('api.v1.sports.facilities.show'), [
                'total_bookings' => $this->bookings()->count(),
                'active_bookings' => $this->activeBookings()->count(),
                'upcoming_bookings' => $this->upcomingBookings()->count(),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
