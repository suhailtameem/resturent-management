<?php

namespace App\Http\Requests\Api\V1\Manager;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateManagerDriverRequest extends FormRequest
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
            'username' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('staff_users', 'username')->ignore($this->route('driver'))],
            'email' => ['sometimes', 'required', 'email', 'max:120', Rule::unique('staff_users', 'email')->ignore($this->route('driver'))],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)],
            'full_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
