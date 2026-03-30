<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['present', 'absent', 'excused', 'leave']),
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
            'marked_at' => [
                'sometimes',
                'date',
                'before_or_equal:now',
            ],
            'note' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'metadata' => [
                'sometimes',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Attendance status is required.',
            'status.in' => 'Attendance status must be one of: present, absent, excused, leave.',
            'reason.required' => 'A reason is required when editing attendance marks.',
            'reason.min' => 'Reason must be at least 10 characters long.',
            'reason.max' => 'Reason must not exceed 500 characters.',
            'marked_at.before_or_equal' => 'Marked date cannot be in the future.',
            'note.max' => 'Note must not exceed 1000 characters.',
        ];
    }
}
