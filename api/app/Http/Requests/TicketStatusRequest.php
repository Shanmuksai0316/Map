<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transition', $this->route('ticket'));
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');
        
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['open', 'in_progress', 'on_hold', 'resolved', 'closed']),
                function ($attribute, $value, $fail) use ($ticket) {
                    if (! $ticket->canTransitionTo($value)) {
                        $fail("Cannot transition from {$ticket->status} to {$value}");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Status must be one of: open, in_progress, on_hold, resolved, closed',
        ];
    }
}
