<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class SentryContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            \Sentry\configureScope(function (Scope $scope) use ($request): void {
                $user = $request->user();
                $scope->setUser([
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]);
            });
        }

        return $next($request);
    }
}
