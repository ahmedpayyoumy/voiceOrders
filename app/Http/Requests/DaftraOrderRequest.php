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
            'selected_customer_id' => 'nullable|integer',
            'selected_product_id' => 'nullable|integer',
            'selected_product_query' => 'nullable|string',
            'selected_products' => 'nullable|array',
            'selected_products.*.product_id' => 'required_with:selected_products|integer',
            'selected_products.*.query' => 'required_with:selected_products|string',
            'confirmed' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.product_name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.price' => 'required_with:items|numeric|min:0',
        ];
    }
}
