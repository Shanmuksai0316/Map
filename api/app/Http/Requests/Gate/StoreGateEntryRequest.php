<?php

namespace App\Http\Requests\Gate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGateEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Guard', 'Campus Manager', 'Rector']) ?? false;
    }

    public function rules(): array
    {
        return [
            'event' => ['required', Rule::in(['entry', 'exit', 'emergency_exit', 'manual_override'])],
            'occurred_at' => ['required', 'date'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'was_offline' => ['boolean'],
            'synced_at' => ['nullable', 'date'],
        ];
    }
}
