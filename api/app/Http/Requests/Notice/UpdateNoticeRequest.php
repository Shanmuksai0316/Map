<?php

namespace App\Http\Requests\Notice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:160'],
            'body' => ['sometimes', 'string'],
            'audience' => ['sometimes', Rule::in(['all_students', 'hostel_students', 'staff'])],
            'channels' => ['nullable', 'array'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
        ];
    }
}
