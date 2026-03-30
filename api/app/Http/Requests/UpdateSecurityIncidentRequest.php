<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecurityIncidentRequest extends FormRequest
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
            'severity' => 'sometimes|in:low,medium,high,critical',
            'location' => 'sometimes|string|max:255',
            'incident_type' => 'sometimes|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'witnesses' => 'nullable|string|max:500',
            'reported_to_police' => 'sometimes|boolean',
            'police_case_number' => 'nullable|string|max:100',
            'status' => 'sometimes|in:open,assigned,in_progress,closed',
            'assigned_to' => 'nullable|exists:users,id',
            'resolution' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Incident title cannot exceed 255 characters',
            'description.max' => 'Incident description cannot exceed 1000 characters',
            'severity.in' => 'Severity must be one of: low, medium, high, critical',
            'location.max' => 'Location cannot exceed 255 characters',
            'incident_type.max' => 'Incident type cannot exceed 100 characters',
            'photo.image' => 'Photo must be an image file',
            'photo.mimes' => 'Photo must be a JPEG, PNG, JPG, or GIF file',
            'photo.max' => 'Photo size cannot exceed 5MB',
            'witnesses.max' => 'Witnesses information cannot exceed 500 characters',
            'police_case_number.max' => 'Police case number cannot exceed 100 characters',
            'status.in' => 'Status must be one of: open, assigned, in_progress, closed',
            'assigned_to.exists' => 'Assigned user does not exist',
            'resolution.max' => 'Resolution cannot exceed 1000 characters',
        ];
    }
}

