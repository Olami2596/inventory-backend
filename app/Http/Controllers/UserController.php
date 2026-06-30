<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return User::where('company_id', auth()->user()->company_id)->get();
    }

    public function deactivate(User $user)
    {
        if ($user->company_id !== auth()->user()->company_id) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->deactivated_at) {
            return response()->json([
                'message' => 'This user is already deactivated.',
            ], 409);
        }

        if ($user->id === auth()->user()->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 403);
        }

        if ($user->role === 'owner') {
            return response()->json([
                'message' => 'The owner cannot be deactivated.',
            ], 403);
        }

        if (auth()->user()->role === 'admin' && $user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot deactivate other admins.',
            ], 403);
        }

        $user->deactivated_at = now();
        $user->save();

        return response()->json($user, 200);
    }

    public function reactivate(User $user)
    {
        if ($user->company_id !== auth()->user()->company_id) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->deactivated_at) {
            return response()->json([
                'message' => 'This user is already active.',
            ], 409);
        }

        if (auth()->user()->role === 'admin' && $user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot reactivate other admins.',
            ], 403);
        }

        $user->deactivated_at = null;
        $user->save();

        return response()->json($user, 200);
    }

    public function revokeAllMyTokens(Request $request)
    {
        auth()->user()->tokens()->delete();

        return response()->json([
            'message' => 'All your sessions have been logged out.',
        ], 200);
    }

    public function revokeUserTokens(User $user)
    {
        if ($user->company_id !== auth()->user()->company_id) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->id === auth()->user()->id) {
            return response()->json([
                'message' => 'Use the self-service endpoint to revoke your own sessions.',
            ], 403);
        }

        if ($user->role === 'owner') {
            return response()->json([
                'message' => 'The owner\'s sessions cannot be revoked by another user.',
            ], 403);
        }

        if (auth()->user()->role === 'admin' && $user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot revoke other admins\' sessions.',
            ], 403);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => 'All sessions for this user have been logged out.',
        ], 200);
    }
}
