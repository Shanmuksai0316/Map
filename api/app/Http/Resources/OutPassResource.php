<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Domain\OutPass\OutPass */
class OutPassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'student_id' => (string) $this->student_id,
            'student_name' => $this->student?->user?->name,
            'hostel_id' => (string) $this->hostel_id,
            'hostel_name' => $this->hostel?->name,
            'reason' => $this->reason->value,
            'overnight' => $this->overnight,
            'status' => $this->status->value,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'note' => $this->note,
            'histories' => $this->histories
                ->sortByDesc('changed_at')
                ->map(fn ($history) => [
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'note' => $history->note,
                    'changed_at' => $history->changed_at?->toIso8601String(),
                    'acted_by' => $history->actor?->name,
                    'timeline_label' => $history->timeline_label,
                    'timeline_description' => $history->timeline_description,
                ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
