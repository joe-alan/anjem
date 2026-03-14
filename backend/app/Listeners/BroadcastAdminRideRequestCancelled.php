<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\RideRequestCancelled;

class BroadcastAdminRideRequestCancelled
{
    public function handle(RideRequestCancelled $event): void
    {
        $request = $event->rideRequest;

        event(new AdminLiveUpdate('request_cancelled', [
            'request_id' => $request->id,
            'rider_id' => $request->rider_id,
            'reason' => $event->reason,
        ]));
    }
}
