<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_name_ar' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'store_name_en' => ['sometimes', 'nullable', 'string', 'min:1', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'support_phone' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^(?:(?:\\+?966)|0)?5\\d{8}$/'],
            'payment_methods' => ['sometimes', 'array:cash_on_delivery_enabled,online_enabled'],
            'payment_methods.cash_on_delivery_enabled' => ['sometimes', 'boolean'],
            'payment_methods.online_enabled' => ['sometimes', 'boolean'],
            'auto_accept_prepaid_orders' => ['sometimes', 'boolean'],
            'currency_code' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['store_name_ar', 'store_name_en', 'address', 'support_phone'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }
}
