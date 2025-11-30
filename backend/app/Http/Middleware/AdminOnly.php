<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminOnly Middleware
 *
 * Ensures that only users with admin role can access protected routes.
 * Requires Sanctum authentication middleware to run first.
 */
class AdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if user has admin role
        if (! $request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        // Check if token has admin abilities (optional - extra security)
        if (! $request->user()->tokenCan('admin:view-users')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin token required.',
            ], 403);
        }

        return $next($request);
    }
}
