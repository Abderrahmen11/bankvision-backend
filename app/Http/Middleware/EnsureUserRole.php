<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // 1. Check if user is authenticated
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 2. Check if user's role matches one of the allowed roles
        if (! empty($roles) && ! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Access forbidden. Required role: ' . implode(', ', $roles) . '.',
            ], 403);
        }

        return $next($request);
    }
}
