<?php

namespace App\Http\Requests\Checklists;

use Illuminate\Foundation\Http\FormRequest;

class SubmitChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

