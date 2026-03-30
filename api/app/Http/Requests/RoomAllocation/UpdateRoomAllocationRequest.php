<?php

declare(strict_types=1);

namespace App\Http\Requests\RoomAllocation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_bed_id' => ['nullable', 'integer', 'exists:room_beds,id'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
