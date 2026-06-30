<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Models\Invitation;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email'),
            ],
            'role' => [
                'required',
                Rule::in(['admin', 'staff']),
            ],
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $pendingInvitationExists = Invitation::where('email', $this->input('email'))
                ->whereNull('accepted_at')
                ->whereNull('cancelled_at')
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingInvitationExists) {
                $validator->errors()->add('email', 'A pending invitation already exists for this email.');
            }
        });
    }
}
