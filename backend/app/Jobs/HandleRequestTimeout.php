<?php

namespace App\Jobs;

use App\Models\RideRequest;
use App\Services\MatchingQueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Safety-net job dispatched when a ride request is sent to a driver.
 * If the driver hasn't responded within the delay window, this job
 * processes the timeout (same as a decline).
 *
 * This guards against the case where the mobile app fails to call
 * the decline endpoint (killed app, network loss, etc.).
 */
class HandleRequestTimeout implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly int $rideRequestId,
        public readonly int $driverId,
    ) {}

    public function handle(MatchingQueueService $matchingQueueService): void
    {
        $rideRequest = RideRequest::find($this->rideRequestId);

        if (! $rideRequest) {
            return;
        }

        // If request is no longer pending, the driver already responded — do nothing
        if (! in_array($rideRequest->status, ['pending', 'matched'])) {
            return;
        }

        // If current_driver_id has changed, a newer dispatch has taken over — do nothing
        if ($rideRequest->current_driver_id !== $this->driverId) {
            return;
        }

        Log::info('Handling ride request timeout via safety-net job', [
            'ride_request_id' => $this->rideRequestId,
            'driver_id' => $this->driverId,
        ]);

        $matchingQueueService->handleDeclineOrTimeout($this->driverId, $this->rideRequestId);
    }
}
