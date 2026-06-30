<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function register(RegisterCompanyRequest $request)
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated) {
            $company = Company::create([
                'name' => $validated['company_name'],
                'email' => $validated['company_email'],
                'phone' => $validated['company_phone'],
                'address' => $validated['company_address'] ?? null,
            ]);

            $user = User::create([
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => $validated['owner_password'],
                'company_id' => $company->id,
                'role' => 'owner',
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'company' => $company,
                'token' => $token,
            ];
        });

        return response()->json($result, 201);
    }
}
