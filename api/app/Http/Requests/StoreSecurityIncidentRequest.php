<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSecurityIncidentRequest extends FormRequest
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
            'severity' => 'required|in:low,medium,high,critical',
            'location' => 'required|string|max:255',
            'incident_type' => 'required|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'witnesses' => 'nullable|string|max:500',
            'reported_to_police' => 'boolean',
            'police_case_number' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Incident title is required',
            'title.max' => 'Incident title cannot exceed 255 characters',
            'description.required' => 'Incident description is required',
            'description.max' => 'Incident description cannot exceed 1000 characters',
            'severity.required' => 'Incident severity is required',
            'severity.in' => 'Severity must be one of: low, medium, high, critical',
            'location.required' => 'Incident location is required',
            'location.max' => 'Location cannot exceed 255 characters',
            'incident_type.required' => 'Incident type is required',
            'incident_type.max' => 'Incident type cannot exceed 100 characters',
            'photo.image' => 'Photo must be an image file',
            'photo.mimes' => 'Photo must be a JPEG, PNG, JPG, or GIF file',
            'photo.max' => 'Photo size cannot exceed 5MB',
            'witnesses.max' => 'Witnesses information cannot exceed 500 characters',
            'police_case_number.max' => 'Police case number cannot exceed 100 characters',
        ];
    }
}

