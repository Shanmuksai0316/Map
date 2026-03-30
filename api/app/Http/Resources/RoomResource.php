<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Room */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'campus_id' => (string) $this->campus_id,
            'hostel_id' => (string) $this->hostel_id,
            'block_code' => $this->block_code,
            'floor_code' => $this->floor_code,
            'number' => $this->number,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'beds' => RoomBedResource::collection($this->whenLoaded('beds')),
        ];
    }
}
