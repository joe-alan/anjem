<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\DriverOnlineStatusChanged;

class BroadcastAdminDriverStatusChange
{
    public function handle(DriverOnlineStatusChanged $event): void
    {
        $driver = $event->driver;

        event(new AdminLiveUpdate('driver_status', [
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'is_online' => $event->isOnline,
            'beacon_id' => $event->beacon?->id,
            'queue_position' => $event->queuePosition,
        ]));
    }
}
