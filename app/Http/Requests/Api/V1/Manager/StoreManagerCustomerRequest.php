<?php

namespace App\Http\Requests\Api\V1\Manager;

use App\Support\SaudiPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreManagerCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'regex:/^(?:\+?966|0)?5[0-9]{8}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (SaudiPhone::isAlreadyUsed((string) $value)) {
                        $fail('This phone number is already assigned to a customer.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:191', 'unique:users,email'],
        ];
    }
}
