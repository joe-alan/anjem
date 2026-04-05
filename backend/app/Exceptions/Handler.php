<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            if (app()->bound('sentry')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) {
                    $scope->setTag('queue', app()->runningInConsole() ? 'console' : 'web');
                });
            }
        });
    }

    /**
     * Determine if the exception should be reported.
     */
    public function shouldReport(Throwable $e): bool
    {
        // Don't report these to Sentry — they're expected in normal API usage
        if ($e instanceof NotFoundHttpException) {
            return false;
        }

        if ($e instanceof ModelNotFoundException) {
            return false;
        }

        if ($e instanceof ValidationException) {
            return false;
        }

        if ($e instanceof AuthenticationException) {
            return false;
        }

        return parent::shouldReport($e);
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

        return redirect()->guest(route('filament.admin.auth.login'));
    }
}
