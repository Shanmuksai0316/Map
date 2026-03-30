<?php

namespace App\Http\Requests\RoomChange;

use Illuminate\Foundation\Http\FormRequest;

class RejectRoomChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('roomChange')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
