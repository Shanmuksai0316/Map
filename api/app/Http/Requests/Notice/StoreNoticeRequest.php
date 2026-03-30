<?php

namespace App\Http\Requests\Notice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'audience' => ['required', Rule::in(['all_students', 'hostel_students', 'staff'])],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'channels' => ['nullable', 'array'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
        ];
    }
}
