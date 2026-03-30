<?php

namespace App\Http\Requests\Room;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Room::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'hostel_id' => ['required', 'integer', 'exists:hostels,id'],
            'block_code' => ['nullable', 'string', 'max:16'],
            'floor_code' => ['nullable', 'string', 'max:16'],
            'number' => ['required', 'string', 'max:16'],
            'capacity' => ['required', 'integer', 'min:1', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'beds' => ['required', 'array', 'min:1'],
            'beds.*.code' => ['required', 'string', 'max:8'],
            'beds.*.status' => ['nullable', Rule::in(['available', 'occupied', 'blocked', 'maintenance'])],
        ];
    }
}
