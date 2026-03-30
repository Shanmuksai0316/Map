<?php

namespace App\Http\Requests\Laundry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaundryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Campus Manager', 'Laundry Staff']) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'service_type' => ['required', Rule::in(['wash_only', 'wash_and_fold', 'dry_clean'])],
            'bag_count' => ['nullable', 'integer', 'min:1', 'max:4'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
