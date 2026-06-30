<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('company_id', auth()->user()->company_id),
            ],

            'type' => [
                'required',
                'string',
                Rule::in(['purchase', 'sale', 'adjustment']),
            ],

            'quantity' => [
                'required',
                'integer',
            ],

            'notes' => ['nullable', 'string'],
        ];
    }
}
