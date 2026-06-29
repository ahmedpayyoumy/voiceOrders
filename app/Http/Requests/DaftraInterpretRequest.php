<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DaftraInterpretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transcript' => 'required|string',
            'daftra_domain' => 'nullable|string|max:255',
            'daftra_api_key' => 'nullable|string',
        ];
    }
}
