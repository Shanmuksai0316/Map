<?php

namespace App\Http\Requests\Imports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class RoomAllotmentImportDryRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tenant_id !== null
            && $user->hasRole('Campus Manager');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', File::types(['csv'])->max(1024)],
        ];
    }
}
