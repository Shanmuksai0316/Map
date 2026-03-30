<?php

namespace App\Http\Requests\Laundry;

use App\Enums\LaundryServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLaundryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_type' => [
                'sometimes',
                'string',
                Rule::in(array_map(fn($type) => $type->value, LaundryServiceType::cases())),
            ],
            'bag_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'special_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_type.in' => 'Invalid service type selected.',
            'bag_count.min' => 'At least 1 bag is required.',
            'bag_count.max' => 'Maximum 10 bags allowed per request.',
            'special_instructions.max' => 'Special instructions cannot exceed 500 characters.',
        ];
    }
}



