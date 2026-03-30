<?php

namespace App\Http\Requests\Sports;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['registered', 'waitlisted', 'attended', 'no_show', 'cancelled'])],
            'notes' => ['nullable', 'string'],
            'attended_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
