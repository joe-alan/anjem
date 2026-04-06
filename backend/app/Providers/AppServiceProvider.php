<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Contract\Storage;
use Kreait\Firebase\Factory;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Firebase services (deferred — only instantiated when resolved)
        $this->app->singleton(Factory::class, function () {
            $projectId = config('services.firebase.project_id');
            if (! $projectId) {
                throw new \RuntimeException('Firebase credentials not configured. Set FIREBASE_PROJECT_ID in .env');
            }

            return (new Factory)->withServiceAccount([
                'type' => 'service_account',
                'project_id' => $projectId,
                'private_key_id' => config('services.firebase.private_key_id'),
                'private_key' => str_replace('\\n', "\n", config('services.firebase.private_key', '')),
                'client_email' => config('services.firebase.client_email'),
                'client_id' => config('services.firebase.client_id'),
                'auth_uri' => config('services.firebase.auth_uri'),
                'token_uri' => config('services.firebase.token_uri'),
            ]);
        });

        $this->app->singleton(Auth::class, function ($app) {
            return $app->make(Factory::class)->createAuth();
        });

        $this->app->singleton(Messaging::class, function ($app) {
            return $app->make(Factory::class)->createMessaging();
        });

        $this->app->singleton(Storage::class, function ($app) {
            $factory = $app->make(Factory::class);
            $bucket = config('services.firebase.storage_bucket');
            if ($bucket) {
                $factory = $factory->withDefaultStorageBucket($bucket);
            }

            return $factory->createStorage();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enable PostGIS spatial queries
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Sanctum::getAccessTokenFromRequestUsing(function (Request $request) {
            $token = $request->bearerToken();

            if ($token) {
                $request->attributes->set('sanctum.bearer_token_present', true);
            }

            return $token;
        });

        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid) {
            $request = app('request');

            if ($accessToken) {
                $request->attributes->set('sanctum.token_record_found', true);
            }

            if ($accessToken && $accessToken->tokenable === null) {
                $request->attributes->set('sanctum.orphaned_token', true);
                $accessToken->delete();

                return false;
            }

            return $isValid;
        });
    }
}
