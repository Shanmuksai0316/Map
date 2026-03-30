<?php

namespace App\Http\Requests\Laundry;

use App\Enums\LaundryRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLaundryRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn($status) => $status->value, LaundryRequestStatus::cases())),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status selected.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $request = $this->route('laundryRequest');
            $newStatus = $this->input('status');
            
            if ($request && $newStatus) {
                $currentStatus = LaundryRequestStatus::from($request->status->value);
                $requestedStatus = LaundryRequestStatus::from($newStatus);
                
                if (!$currentStatus->canTransitionTo($requestedStatus)) {
                    $validator->errors()->add('status', 
                        "Cannot transition from {$currentStatus->value} to {$requestedStatus->value}."
                    );
                }
            }
        });
    }
}



