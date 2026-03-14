<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\RideStatusUpdated;

class BroadcastAdminDriverRejoined
{
    public function handle(RideStatusUpdated $event): void
    {
        $ride = $event->ride;

        if (
            in_array($ride->status, ['completed', 'cancelled']) &&
            $ride->driver_id !== null
        ) {
            event(new AdminLiveUpdate('driver_rejoined', [
                'driver_id' => $ride->driver_id,
                'ride_id' => $ride->id,
                'completed_status' => $ride->status,
            ]));
        }
    }
}
