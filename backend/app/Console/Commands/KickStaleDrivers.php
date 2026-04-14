<?php

namespace App\Console\Commands;

use App\Events\DriverOnlineStatusChanged;
use App\Models\DriverProfile;
use App\Services\MatchingQueueService;
use Illuminate\Console\Command;

class KickStaleDrivers extends Command
{
    protected $signature = 'drivers:kick-stale
                            {--threshold=180 : Seconds since last heartbeat before a driver is kicked}
                            {--dry-run : Log who would be kicked without actually kicking them}';

    protected $description = 'Remove from queue and mark offline any driver whose last heartbeat is stale (app crash / force-quit)';

    public function handle(MatchingQueueService $queue): int
    {
        $threshold = (int) $this->option('threshold');
        $dryRun    = $this->option('dry-run');
        $cutoff    = now()->subSeconds($threshold);

        // Only target drivers who are in the matching queue (went_online_at IS NOT NULL
        // AND queue_joined_at IS NOT NULL). Drivers in an active ride have queue_joined_at
        // cleared by RideController::accept(), so they are never affected.
        $stale = DriverProfile::query()
            ->whereNotNull('went_online_at')
            ->whereNotNull('queue_joined_at')
            ->where(function ($q) use ($cutoff) {
                // Null heartbeat only counts as stale if the driver has also been
                // online longer than the threshold — prevents kicking drivers who
                // just went online before their first location update arrives.
                $q->where(function ($inner) use ($cutoff) {
                    $inner->whereNull('last_location_update')
                          ->where('went_online_at', '<', $cutoff);
                })->orWhere('last_location_update', '<', $cutoff);
            })
            ->with('user')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale drivers found.');
            return self::SUCCESS;
        }

        foreach ($stale as $profile) {
            $driver = $profile->user;
            $age    = $profile->last_location_update
                ? (int) $profile->last_location_update->diffInSeconds(now())
                : null;

            $this->warn(sprintf(
                '[%s] driver_id=%d  last_heartbeat=%s',
                $dryRun ? 'DRY-RUN' : 'KICKING',
                $driver->id,
                $age !== null ? "{$age}s ago" : 'never'
            ));

            if ($dryRun) {
                continue;
            }

            $queue->removeFromQueue($driver->id);
            $profile->update(['went_online_at' => null]);
            broadcast(new DriverOnlineStatusChanged($driver, false, null, null, 'location_stale'));

            \Log::warning('KickStaleDrivers: driver force-kicked offline', [
                'driver_id'            => $driver->id,
                'driver_name'          => $driver->name,
                'last_location_update' => $profile->last_location_update?->toISOString(),
                'stale_seconds'        => $age,
                'went_online_at'       => $profile->went_online_at?->toISOString(),
                'online_duration_s'    => $profile->went_online_at
                    ? (int) $profile->went_online_at->diffInSeconds(now())
                    : null,
                'queue_joined_at'      => $profile->queue_joined_at?->toISOString(),
                'had_any_heartbeat'    => $profile->last_location_update !== null,
                'threshold_seconds'    => $threshold,
            ]);
        }

        $count = $stale->count();
        $this->info($dryRun
            ? "Would kick {$count} driver(s)."
            : "Kicked {$count} stale driver(s) offline.");

        return self::SUCCESS;
    }
}
