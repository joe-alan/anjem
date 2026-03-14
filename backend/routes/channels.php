<?php

use App\Models\Location;
use App\Models\Ride;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User-specific private channels
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Driver-specific private channels
Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
    // Only the driver themselves can listen to their channel
    return (int) $user->id === (int) $driverId && $user->isDriver();
});

// Ride-specific private channels
Broadcast::channel('ride.{rideId}', function ($user, $rideId) {
    // Only the rider or driver of the specific ride can listen
    $ride = Ride::find($rideId);

    if (! $ride) {
        return false;
    }

    return (int) $user->id === (int) $ride->rider_id ||
           (int) $user->id === (int) $ride->driver_id;
});

// Admin live updates channel (session-authenticated admin users only)
Broadcast::channel('admin.live', function ($user) {
    return $user->isAdmin();
});

// Public beacon channels (no authentication required)
// These are rate-limited and contain only aggregate data
Broadcast::channel('beacon.{beaconId}', function ($user, $beaconId) {
    // Verify beacon exists and is active
    $beacon = Location::where('id', $beaconId)
        ->where('is_beacon', true)
        ->where('is_active', true)
        ->exists();

    return $beacon;
});
