<?php

namespace App\Events;

use App\Models\RideRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideRequestCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RideRequest $rideRequest,
        public string $cancelledBy = 'admin',
        public ?string $reason = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("user.{$this->rideRequest->rider_id}"),
        ];

        // Notify the currently-dispatched driver on their driver channel
        if ($this->rideRequest->current_driver_id) {
            $channels[] = new PrivateChannel("driver.{$this->rideRequest->current_driver_id}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->rideRequest->id,
            'rider_id' => $this->rideRequest->rider_id,
            'cancelled_by' => $this->cancelledBy,
            'reason' => $this->reason,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ride.request.cancelled';
    }
}
