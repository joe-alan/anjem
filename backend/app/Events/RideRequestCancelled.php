<?php

namespace App\Events;

use App\Models\RideRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideRequestCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private readonly ?int $currentDriverId;

    public function __construct(
        public RideRequest $rideRequest,
        public string $cancelledBy = 'admin',
        public ?string $reason = null,
        ?int $currentDriverId = null,
        // false when re-dispatching to the next driver — rider stays on waiting screen.
        // true for terminal cancellations (rider cancel, admin cancel, expiry).
        public bool $notifyRider = true,
    ) {
        // Capture the driver ID at construction time before the model is
        // serialised onto the queue — by the time it is re-hydrated,
        // current_driver_id may already have been cleared.
        $this->currentDriverId = $currentDriverId ?? $rideRequest->current_driver_id;
    }

    public function broadcastOn(): array
    {
        $channels = [];

        // Only send to the rider channel on terminal cancellations, not re-dispatches.
        if ($this->notifyRider) {
            $channels[] = new PrivateChannel("user.{$this->rideRequest->rider_id}");
        }

        // Always dismiss the driver whose request card is being cleared.
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
