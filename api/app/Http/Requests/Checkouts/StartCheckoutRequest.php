<?php

namespace App\Http\Requests\Checkouts;

use Illuminate\Foundation\Http\FormRequest;

class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Campus Manager') ?? false;
    }

    public function rules(): array
    {
        return [
            'inspection_passed' => ['nullable', 'boolean'],
            'keys_collected' => ['nullable', 'boolean'],
            'dues_cleared' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string'],
        ];
    }
}
