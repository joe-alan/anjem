<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\DriverCreditsUpdated;

class BroadcastAdminCreditsUpdate
{
    public function handle(DriverCreditsUpdated $event): void
    {
        $driver = $event->driver;
        $creditsBefore = $event->operation === 'grant'
            ? $event->newBalance - $event->amount
            : $event->newBalance + $event->amount;

        event(new AdminLiveUpdate('credits_updated', [
            'driver_id' => $driver->id,
            'credits_before' => $creditsBefore,
            'credits_after' => $event->newBalance,
            'reason' => $event->operation,
        ]));
    }
}
