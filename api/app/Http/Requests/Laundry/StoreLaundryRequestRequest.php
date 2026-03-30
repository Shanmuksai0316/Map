<?php

namespace App\Http\Requests\Laundry;

use App\Enums\LaundryServiceType;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaundryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                'exists:students,id',
                function ($attribute, $value, $fail) {
                    $student = Student::find($value);
                    if ($student && $student->tenant_id !== auth()->user()->tenant_id) {
                        $fail('The selected student does not belong to your organization.');
                    }
                },
            ],
            'service_type' => [
                'required',
                'string',
                Rule::in(array_map(fn($type) => $type->value, LaundryServiceType::cases())),
            ],
            'bag_count' => ['required', 'integer', 'min:1', 'max:10'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
            'hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student ID is required.',
            'student_id.exists' => 'The selected student does not exist.',
            'service_type.required' => 'Service type is required.',
            'service_type.in' => 'Invalid service type selected.',
            'bag_count.required' => 'Bag count is required.',
            'bag_count.min' => 'At least 1 bag is required.',
            'bag_count.max' => 'Maximum 10 bags allowed per request.',
            'special_instructions.max' => 'Special instructions cannot exceed 500 characters.',
            'hostel_id.exists' => 'The selected hostel does not exist.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $studentId = $this->input('student_id');
            
            if ($studentId) {
                $student = Student::with('user')->find($studentId);
                
                if ($student && $student->user) {
                    // Check if student is not archived
                    if ($student->user->archived) {
                        $validator->errors()->add('student_id', 'The selected student is not active.');
                    }
                    
                    // Check if student belongs to the same tenant
                    if ($student->tenant_id !== auth()->user()->tenant_id) {
                        $validator->errors()->add('student_id', 'The selected student does not belong to your organization.');
                    }
                }
            }
        });
    }
}
