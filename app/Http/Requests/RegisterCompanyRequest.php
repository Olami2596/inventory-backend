<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterCompanyRequest extends FormRequest
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
            'company_name' => ['required', 'string'],
            'company_email' => ['required', 'email', 'unique:companies,email'],
            'company_phone' => ['required', 'string'],
            'company_address' => ['nullable', 'string'],

            'owner_name' => ['required', 'string'],
            'owner_email' => ['required', 'email', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
