<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\NewRideRequest;

class BroadcastAdminMatchingDispatch
{
    public function handle(NewRideRequest $event): void
    {
        event(new AdminLiveUpdate('matching_dispatch', [
            'request_id' => $event->rideRequest->id,
            'driver_ids' => $event->driverIds,
        ]));
    }
}
