<?php

namespace App\Http\Requests\Sports;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSportsEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ?? false;
    }

    public function rules(): array
    {
        return [
            'sport' => ['sometimes', 'string', 'max:80'],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['sometimes', 'date'],
            'end_time' => ['nullable', 'date', 'after:scheduled_at'],
            'registration_deadline' => ['nullable', 'date', 'before:scheduled_at'],
            'requirements' => ['nullable', 'string'],
            'venue' => ['nullable', 'string', 'max:160'],
            'status' => ['sometimes', Rule::in(['scheduled', 'ongoing', 'completed', 'cancelled'])],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
