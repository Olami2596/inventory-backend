<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    // public function logout(Request $request)
    // {
    //     $token = $request->user()->currentAccessToken();

    //     logger('Before delete', [
    //         'token_id' => $token?->id,
    //     ]);

    //     $token->delete();

    //     logger('After delete');

    //     return response()->json([
    //         'message' => 'Logged out successfully',
    //     ]);
    // }
}
