<?php

declare(strict_types=1);

namespace App\Http\Requests\RoomAllocation;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'room_bed_id' => ['required', 'integer', 'exists:room_beds,id'],
            'effective_from' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
