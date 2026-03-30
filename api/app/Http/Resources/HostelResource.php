<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Hostel */
class HostelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'campus_id' => (string) $this->campus_id,
            'name' => $this->name,
            'code' => $this->code,
            'gender_mode' => $this->gender_mode,
            'overnight_enabled' => (bool) $this->overnight_enabled,
        ];
    }
}
