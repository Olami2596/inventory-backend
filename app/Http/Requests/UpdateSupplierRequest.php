<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($this->route('supplier')->id),
            ],
            'contact_name' => ['nullable', 'string'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('suppliers')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($this->route('supplier')->id),
            ],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ];
    }
}
