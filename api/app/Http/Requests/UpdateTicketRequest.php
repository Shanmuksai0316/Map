<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'category' => 'sometimes|string|max:100',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'location' => 'sometimes|string|max:255',
            'due_date' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Ticket title cannot exceed 255 characters',
            'description.max' => 'Ticket description cannot exceed 1000 characters',
            'category.max' => 'Category cannot exceed 100 characters',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent',
            'location.max' => 'Location cannot exceed 255 characters',
            'due_date.date' => 'Due date must be a valid date',
            'tags.array' => 'Tags must be an array',
            'tags.*.string' => 'Each tag must be a string',
            'tags.*.max' => 'Each tag cannot exceed 50 characters',
            'status.in' => 'Status must be one of: open, in_progress, resolved, closed',
            'assigned_to.exists' => 'Assigned user does not exist',
        ];
    }
}
