<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('room')) ?? false;
    }

    public function rules(): array
    {
        return [
            'block_code' => ['nullable', 'string', 'max:16'],
            'floor_code' => ['nullable', 'string', 'max:16'],
            'number' => ['sometimes', 'string', 'max:16'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'beds' => ['sometimes', 'array'],
            'beds.*.id' => ['nullable', 'integer', 'exists:room_beds,id'],
            'beds.*.code' => ['required_with:beds.*.id', 'string', 'max:8'],
            'beds.*.status' => ['nullable', Rule::in(['available', 'occupied', 'blocked', 'maintenance'])],
        ];
    }
}
