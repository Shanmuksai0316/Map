<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'assignee_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'assignee_user_id.required' => 'Assignee is required',
            'assignee_user_id.exists' => 'Selected assignee does not exist',
        ];
    }
}
