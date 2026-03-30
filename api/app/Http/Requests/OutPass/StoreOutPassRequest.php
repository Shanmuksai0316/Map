<?php

namespace App\Http\Requests\OutPass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'reason' => ['required', Rule::in(['normal', 'leave', 'sick'])],
            'overnight' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ];
    }
}
