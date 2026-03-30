<?php

namespace App\Http\Requests\Sports;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSportsEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ?? false;
    }

    public function rules(): array
    {
        return [
            'sport' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'end_time' => ['nullable', 'date', 'after:scheduled_at'],
            'registration_deadline' => ['nullable', 'date', 'before:scheduled_at'],
            'requirements' => ['nullable', 'string'],
            'venue' => ['nullable', 'string', 'max:160'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
