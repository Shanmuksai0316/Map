<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Campus Manager', 'Rector']) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['roll_call', 'event', 'night_check'])],
            'scheduled_at' => ['required', 'date'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
