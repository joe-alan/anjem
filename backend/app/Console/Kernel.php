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
