<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\NewRideRequest;

class BroadcastAdminNewRideRequest
{
    public function handle(NewRideRequest $event): void
    {
        $request = $event->rideRequest;

        event(new AdminLiveUpdate('new_request', [
            'request_id' => $request->id,
            'rider_id' => $request->rider_id,
            'rider_name' => $request->rider?->name,
            'pickup' => $request->pickupLocation?->name,
            'destination' => $request->destinationLocation?->name,
            'passenger_count' => $request->passenger_count,
            'driver_ids' => $event->driverIds,
        ]));
    }
}
