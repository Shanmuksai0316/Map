<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RoomBed */
class RoomBedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'room_id' => (string) $this->room_id,
            'hostel_id' => (string) $this->hostel_id,
            'code' => $this->code,
            'status' => $this->status,
            'occupied_at' => $this->occupied_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'blocked_periods' => RoomBlockedBedResource::collection($this->whenLoaded('blockedPeriods')),
        ];
    }
}
