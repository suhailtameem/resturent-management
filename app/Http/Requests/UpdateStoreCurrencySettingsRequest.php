<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreCurrencySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_label_ar' => ['required', 'string', 'min:1', 'max:20'],
            'currency_label_en' => ['required', 'string', 'min:1', 'max:20'],
            'currency_code' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['currency_label_ar', 'currency_label_en'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }
}
