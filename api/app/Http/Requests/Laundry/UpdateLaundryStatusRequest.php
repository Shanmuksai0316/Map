<?php

namespace App\Http\Requests\Laundry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLaundryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Campus Manager', 'Laundry Staff']) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['queued', 'washing', 'drying', 'ready', 'completed', 'cancelled'])],
            'laundry_cycle_id' => ['nullable', 'integer', 'exists:laundry_cycles,id'],
            'ready_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
