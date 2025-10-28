<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * For API-only applications, always return null to send JSON 401 response
     */
    protected function redirectTo(Request $request): ?string
    {
        // Always return null for API requests (no login route needed)
        return null;
    }
}
