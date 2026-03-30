<?php

namespace App\Http\Requests\OutPass;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:approved,declined'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
