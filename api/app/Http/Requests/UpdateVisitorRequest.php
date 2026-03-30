<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitorRequest extends FormRequest
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
            'visitor_name' => 'sometimes|string|max:255',
            'visitor_phone' => 'sometimes|string|max:20',
            'visitor_id_type' => 'sometimes|in:aadhar,pan,driving_license,passport,other',
            'visitor_id_number' => 'sometimes|string|max:50',
            'student_id' => 'sometimes|exists:students,id',
            'purpose' => 'sometimes|string|max:500',
            'expected_duration' => 'nullable|integer|min:1|max:480', // max 8 hours in minutes
            'vehicle_number' => 'nullable|string|max:20',
            'accompanying_persons' => 'nullable|integer|min:0|max:10',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,allowed,denied,exited',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'visitor_name.max' => 'Visitor name cannot exceed 255 characters',
            'visitor_phone.max' => 'Phone number cannot exceed 20 characters',
            'visitor_id_type.in' => 'ID type must be one of: aadhar, pan, driving_license, passport, other',
            'visitor_id_number.max' => 'ID number cannot exceed 50 characters',
            'student_id.exists' => 'Selected student does not exist',
            'purpose.max' => 'Purpose cannot exceed 500 characters',
            'expected_duration.integer' => 'Expected duration must be a number',
            'expected_duration.min' => 'Expected duration must be at least 1 minute',
            'expected_duration.max' => 'Expected duration cannot exceed 8 hours',
            'vehicle_number.max' => 'Vehicle number cannot exceed 20 characters',
            'accompanying_persons.integer' => 'Accompanying persons must be a number',
            'accompanying_persons.min' => 'Accompanying persons cannot be negative',
            'accompanying_persons.max' => 'Accompanying persons cannot exceed 10',
            'notes.max' => 'Notes cannot exceed 1000 characters',
            'status.in' => 'Status must be one of: pending, allowed, denied, exited',
        ];
    }
}

