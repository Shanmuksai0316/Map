<?php

namespace App\Http\Requests\AutoAllocation;

use Illuminate\Foundation\Http\FormRequest;

class AutoAllocationPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\RoomAllocation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}

