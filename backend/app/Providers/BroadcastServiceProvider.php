<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mobile API (bearer token)
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        // Admin panel (session-based)
        Broadcast::routes([
            'prefix' => 'admin',
            'middleware' => ['web', 'auth'],
        ]);

        require base_path('routes/channels.php');
    }
}
