<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\RideStatusUpdated;

class BroadcastAdminRideStatusUpdate
{
    public function handle(RideStatusUpdated $event): void
    {
        $ride = $event->ride;

        event(new AdminLiveUpdate('ride_status', [
            'ride_id' => $ride->id,
            'status' => $ride->status,
            'previous_status' => $event->previousStatus,
            'rider_id' => $ride->rider_id,
            'driver_id' => $ride->driver_id,
            'pickup' => $ride->pickupLocation?->name,
            'destination' => $ride->destinationLocation?->name,
            'admin_override' => $event->adminOverride,
        ]));
    }
}
