<?php

namespace App\Http\Requests\Laundry;

use Illuminate\Foundation\Http\FormRequest;

class StoreLaundryCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Campus Manager', 'Laundry Staff']) ?? false;
    }

    public function rules(): array
    {
        return [
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'machine_label' => ['required', 'string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
