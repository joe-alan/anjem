<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Always return JSON for API routes
        if ($request->is('api/*') || $request->expectsJson()) {
            $orphanedToken = (bool) $request->attributes->get('sanctum.orphaned_token');
            $bearerProvided = (bool) $request->attributes->get('sanctum.bearer_token_present');
            $tokenRecordFound = (bool) $request->attributes->get('sanctum.token_record_found');

            $invalidToken = $orphanedToken || ($bearerProvided && ! $tokenRecordFound);

            return response()->json([
                'success' => false,
                'code' => $invalidToken ? 'AUTH_USER_NOT_FOUND' : 'AUTH_UNAUTHENTICATED',
                'message' => $invalidToken
                    ? 'Your account is no longer available. Please sign in again.'
                    : 'Authentication required. Please sign in again.',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
