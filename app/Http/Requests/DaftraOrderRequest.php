<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DaftraOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transcript' => 'required|string',
            'daftra_domain' => 'required|string|max:255',
            'daftra_api_key' => 'required|string',
        ];
    }
}
