<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Campus Manager', 'Rector', 'Guard']) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'marked_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
