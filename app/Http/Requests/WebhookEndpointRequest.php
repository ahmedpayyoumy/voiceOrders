<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE',
            'headers' => 'sometimes|nullable|array',
            'headers.*.key' => 'required_with:headers|string',
            'headers.*.value' => 'required_with:headers|string',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'headers.*.key.required_with' => 'Each header must have a key.',
            'headers.*.value.required_with' => 'Each header must have a value.',
        ];
    }
}
