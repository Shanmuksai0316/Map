<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Student */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'user_id' => (string) $this->user_id,
            'map_student_id' => $this->map_student_id,
            'student_uid' => $this->student_uid,
            'roll_no' => $this->roll_no,
            'program' => $this->program,
            'year_of_study' => $this->year_of_study,
            'admission_year' => $this->admission_year,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
