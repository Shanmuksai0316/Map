<?php

namespace App\Http\Requests\Checklists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', Rule::in(['Done', 'NA'])],
            'comment' => ['nullable', 'string', 'max:500'],
            'photo_urls' => ['nullable', 'array', 'max:3'],
            'photo_urls.*' => ['string', 'url', 'starts_with:https://'],
        ];
    }
}

