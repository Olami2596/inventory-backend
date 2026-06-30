<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($request->user()->deactivated_at) {
            return response()->json([
                'message' => 'Your account has been deactivated.',
            ], 403);
        }

        return $next($request);
    }
}
