<?php

namespace App\Http\Requests\Checkouts;

use Illuminate\Foundation\Http\FormRequest;

class CompleteCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Campus Manager') ?? false;
    }

    public function rules(): array
    {
        return [
            'inspection_passed' => ['required', 'boolean'],
            'keys_collected' => ['required', 'boolean'],
            'dues_cleared' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string'],
        ];
    }
}
