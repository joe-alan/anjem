<?php

namespace App\Events;

use App\Models\RideRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the rider when a new driver joins the queue and their
 * previously-unmatched request is being re-dispatched.
 *
 * The rider's "No Drivers Available" countdown screen listens for this
 * event and transitions back to the "Finding Driver" searching view.
 */
class RideSearchResumed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly RideRequest $rideRequest,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->rideRequest->rider_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->rideRequest->id,
            'timestamp'  => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ride.search.resumed';
    }
}
