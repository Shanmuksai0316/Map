<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'attachments' => ['sometimes', 'array', 'max:3'],
            'attachments.*' => ['string', 'url'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Comment body is required',
            'body.max' => 'Comment cannot exceed 2000 characters',
            'attachments.max' => 'Maximum 3 attachments allowed',
        ];
    }
}
