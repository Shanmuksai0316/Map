<?php

namespace App\Http\Requests\RoomChange;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRoomChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve', $this->route('roomChange')) ?? false;
    }

    public function rules(): array
    {
        return [
            'room_bed_id' => ['required', 'exists:room_beds,id'],
            'effective_from' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
