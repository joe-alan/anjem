<?php

namespace App\Jobs;

use App\Events\RideRequestCancelled;
use App\Models\RideRequest;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched with a 60-second delay when no driver is available for a request.
 * Expires the request and broadcasts to the rider so they are kicked off the
 * waiting screen and placed in the rider cooldown window.
 */
class ExpireRideRequest implements ShouldQueue
{
    use Dispatchable, Queueable;

    private const RIDER_COOLDOWN_SECONDS = 60;

    public function __construct(
        public readonly int $rideRequestId,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $rideRequest = RideRequest::find($this->rideRequestId);

        if (! $rideRequest) {
            return;
        }

        // Idempotency: only act if still pending (may have been matched since)
        if ($rideRequest->status !== 'pending') {
            return;
        }

        // A new driver was dispatched during the no-drivers countdown window.
        // Defer expiry so the driver has time to respond (another full countdown).
        if ($rideRequest->current_driver_id !== null) {
            self::dispatch($this->rideRequestId)
                ->delay(now()->addSeconds(self::RIDER_COOLDOWN_SECONDS));

            Log::info('ExpireRideRequest deferred: driver dispatched during countdown', [
                'ride_request_id'   => $this->rideRequestId,
                'current_driver_id' => $rideRequest->current_driver_id,
            ]);

            return;
        }

        // No rider cooldown on system expiry — rider waited it out, not their fault
        $rideRequest->update([
            'status' => 'expired',
        ]);

        // Broadcast to rider to kick them off the waiting screen
        try {
            broadcast(new RideRequestCancelled($rideRequest, 'system'));
        } catch (\Exception $e) {
            Log::warning('ExpireRideRequest: failed to broadcast expiry to rider', [
                'ride_request_id' => $this->rideRequestId,
                'error'           => $e->getMessage(),
            ]);
        }

        // Push notification
        try {
            $rideRequest->loadMissing('rider');
            if ($rideRequest->rider) {
                $notificationService->sendRideRequestTimeoutToRider($rideRequest);
            }
        } catch (\Exception $e) {
            Log::error('ExpireRideRequest: failed to send push notification', [
                'ride_request_id' => $this->rideRequestId,
                'error'           => $e->getMessage(),
            ]);
        }

        Log::info('Ride request expired after no-drivers countdown', [
            'ride_request_id' => $this->rideRequestId,
        ]);
    }
}
