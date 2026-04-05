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
        if (app()->bound('sentry')) {
            \Sentry\configureScope(function (Scope $scope) use ($request): void {
                if ($request->user()) {
                    $user = $request->user();
                    $scope->setUser([
                        'id' => $user->id,
                        'email' => $user->email,
                    ]);
                    $scope->setTag('user.role', $user->role ?? 'unknown');
                }

                $scope->setTag('api.route', $request->route()?->getName() ?? $request->path());
            });
        }

        return $next($request);
    }
}
