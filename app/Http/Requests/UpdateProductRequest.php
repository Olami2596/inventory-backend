<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('products')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($this->route('product')->id),
            ],

            'description' => ['nullable', 'string'],

            'price' => ['sometimes', 'required', 'numeric', 'min:0'],

            'cost' => ['nullable', 'numeric', 'min:0'],

            'image_url' => ['nullable', 'url'],

            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('company_id', auth()->user()->company_id),
            ],

            'supplier_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('company_id', auth()->user()->company_id),
            ],
        ];
    }
}
