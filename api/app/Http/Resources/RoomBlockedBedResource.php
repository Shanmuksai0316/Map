<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RoomBlockedBed */
class RoomBlockedBedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'room_bed_id' => (string) $this->room_bed_id,
            'blocked_by' => (string) $this->blocked_by,
            'reason' => $this->reason,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'effective_to' => $this->effective_to?->toIso8601String(),
        ];
    }
}
