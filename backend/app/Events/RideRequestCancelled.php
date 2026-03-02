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

    private readonly ?int $currentDriverId;

    public function __construct(
        public RideRequest $rideRequest,
        public string $cancelledBy = 'admin',
        public ?string $reason = null,
        ?int $currentDriverId = null,
    ) {
        // Capture the driver ID at construction time before the model is
        // serialised onto the queue — by the time it is re-hydrated,
        // current_driver_id may already have been cleared.
        $this->currentDriverId = $currentDriverId ?? $rideRequest->current_driver_id;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("user.{$this->rideRequest->rider_id}"),
        ];

        // Notify the currently-dispatched driver on their driver channel
        if ($this->currentDriverId) {
            $channels[] = new PrivateChannel("driver.{$this->currentDriverId}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'request_id'          => $this->rideRequest->id,
            'rider_id'            => $this->rideRequest->rider_id,
            'cancelled_by'        => $this->cancelledBy,
            'reason'              => $this->reason,
            'rider_cooldown_until' => $this->rideRequest->rider_cooldown_until?->toISOString(),
            'timestamp'           => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ride.request.cancelled';
    }
}
