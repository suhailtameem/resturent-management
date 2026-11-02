<?php

namespace App\Http\Requests\Api\V1\Manager;

use App\Models\User;
use App\Support\SaudiPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagerCustomerRequest extends FormRequest
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
            'full_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^(?:\+?966|0)?5[0-9]{8}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $customer = $this->route('customer');
                    $customerId = $customer instanceof User ? (int) $customer->id : (int) $customer;
                    if (SaudiPhone::isAlreadyUsed((string) $value, $customerId)) {
                        $fail('This phone number is already assigned to another customer.');
                    }
                },
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($this->route('customer')),
            ],
            'is_vip' => ['sometimes', 'boolean'],
        ];
    }
}
