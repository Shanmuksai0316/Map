<?php

namespace App\Http\Requests\AutoAllocation;

use Illuminate\Foundation\Http\FormRequest;

class AutoAllocationCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\RoomAllocation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'effective_from' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'allocations.*.room_bed_id' => ['required', 'integer', 'exists:room_beds,id'],
            'allocations.*.effective_from' => ['nullable', 'date'],
            'allocations.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
}

