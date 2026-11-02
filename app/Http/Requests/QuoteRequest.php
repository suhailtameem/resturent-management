<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_mode' => ['required', 'in:pickup,delivery'],
            'address_id' => ['required_if:delivery_mode,delivery', 'nullable', 'integer'],
            'neighborhood_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.size_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.flavor_id' => ['nullable', 'integer', 'exists:product_options,id'],
            'items.*.addon_ids' => ['sometimes', 'array', 'max:20'],
            'items.*.addon_ids.*' => ['integer', 'distinct', 'exists:product_options,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
        ];
    }
}
