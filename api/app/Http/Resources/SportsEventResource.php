<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SportsEvent */
class SportsEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'campus_id' => $this->campus_id ? (string) $this->campus_id : null,
            'hostel_id' => $this->hostel_id ? (string) $this->hostel_id : null,
            'sport' => $this->sport,
            'name' => $this->name,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'venue' => $this->venue,
            'capacity' => $this->capacity,
            'metadata' => $this->metadata,
            'enrollments_count' => $this->when(isset($this->enrollments_count), $this->enrollments_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
