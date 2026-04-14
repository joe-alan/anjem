<?php

namespace App\Console;

use App\Services\MatchingQueueService;
use App\Services\RideService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Expire stale pending ride requests and clean up timed-out queue dispatches
        $schedule->call(function () {
            app(RideService::class)->cleanupExpiredRequests();
            app(MatchingQueueService::class)->cleanupTimedOutRequests();
        })->everyFiveMinutes()->name('cleanup-expired-requests')->withoutOverlapping();

        // Kick drivers who stopped sending heartbeats (app crash / force-quit).
        // The mobile idle location stream sends every ~25 s; 180 s = ~7 missed
        // heartbeats — generous enough to absorb OEM background throttling
        // while still cleaning up genuinely dead drivers within 3 minutes.
        $schedule->command('drivers:kick-stale --threshold=180')
            ->everyMinute()
            ->name('kick-stale-drivers')
            ->withoutOverlapping()
            ->runInBackground();

        // Restore queue_joined_at for drivers whose decline cooldown has expired.
        $schedule->call(function () {
            app(MatchingQueueService::class)->reactivateCooldownExpiredDrivers();
        })->everyMinute()->name('reactivate-cooldown-expired-drivers')->withoutOverlapping();

        // Delete route cache entries older than 30 days to prevent unbounded table growth.
        $schedule->call(function () {
            app(\App\Services\RouteCacheService::class)->cleanupStaleRoutes(30);
        })->daily()->at('03:00')->name('cleanup-stale-routes')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
