<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Mail\PasswordResetMail;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $passwordReset = PasswordReset::create(['email' => $request->email]);
            Mail::to($passwordReset->email)->send(new PasswordResetMail($passwordReset));
        }

        return response()->json([
            'message' => 'If an account exists with that email, a password reset link has been sent.',
        ], 200);
    }

    public function reset(ResetPasswordRequest $request)
    {
        $validated = $request->validated();

        $passwordReset = PasswordReset::where('token', $validated['token'])->first();

        if (!$passwordReset || $passwordReset->used_at || $passwordReset->expires_at->isPast()) {
            return response()->json([
                'message' => 'This password reset link is invalid or has expired.',
            ], 410);
        }

        $user = User::where('email', $passwordReset->email)->first();

        $user->password = $validated['password'];
        $user->save();

        $passwordReset->used_at = now();
        $passwordReset->save();

        return response()->json([
            'message' => 'Your password has been reset successfully. You may now log in.',
        ], 200);
    }
}
