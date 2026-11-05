<?php

namespace App\Http\Requests\Api\V1\Manager;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagerProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('staff_api')?->role === 'super_admin';
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
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('staff_users', 'username')->ignore($this->user('staff_api')?->getAuthIdentifier()),
            ],
            'email' => [
                'required',
                'email',
                'max:120',
                Rule::unique('staff_users', 'email')->ignore($this->user('staff_api')?->getAuthIdentifier()),
            ],
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
