<?php

namespace App\Http\Requests\Sports;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollSportsEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['registered', 'waitlisted'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
