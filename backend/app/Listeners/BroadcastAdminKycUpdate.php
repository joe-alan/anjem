<?php

namespace App\Listeners;

use App\Events\AdminLiveUpdate;
use App\Events\DriverKycStatusChanged;

class BroadcastAdminKycUpdate
{
    public function handle(DriverKycStatusChanged $event): void
    {
        $driver = $event->driver;

        event(new AdminLiveUpdate('kyc_updated', [
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'kyc_status' => $event->isVerified ? 'verified' : 'rejected',
        ]));
    }
}
