<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'category' => 'required|string|max:100',
            'priority' => 'required|in:low,medium,high,urgent',
            'location' => 'required|string|max:255',
            'due_date' => 'nullable|date|after:now',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Ticket title is required',
            'title.max' => 'Ticket title cannot exceed 255 characters',
            'description.required' => 'Ticket description is required',
            'description.max' => 'Ticket description cannot exceed 1000 characters',
            'category.required' => 'Ticket category is required',
            'category.max' => 'Category cannot exceed 100 characters',
            'priority.required' => 'Ticket priority is required',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent',
            'location.required' => 'Location is required',
            'location.max' => 'Location cannot exceed 255 characters',
            'due_date.date' => 'Due date must be a valid date',
            'due_date.after' => 'Due date must be in the future',
            'tags.array' => 'Tags must be an array',
            'tags.*.string' => 'Each tag must be a string',
            'tags.*.max' => 'Each tag cannot exceed 50 characters',
            'assigned_to.exists' => 'Assigned user does not exist',
        ];
    }
}
