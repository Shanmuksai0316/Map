<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GateEntry */
class GateEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'campus_id' => $this->campus_id ? (string) $this->campus_id : null,
            'hostel_id' => $this->hostel_id ? (string) $this->hostel_id : null,
            'guard_id' => $this->guard_id ? (string) $this->guard_id : null,
            'student_id' => $this->student_id ? (string) $this->student_id : null,
            'event' => $this->event,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'source' => $this->source,
            'was_offline' => $this->was_offline,
            'synced_at' => $this->synced_at?->toIso8601String(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
