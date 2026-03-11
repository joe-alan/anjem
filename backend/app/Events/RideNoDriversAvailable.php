<?php

namespace App\Events;

use App\Models\RideRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideNoDriversAvailable implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly RideRequest $rideRequest,
        public readonly int $countdownSeconds = 60,
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
            'request_id'        => $this->rideRequest->id,
            'countdown_seconds' => $this->countdownSeconds,
            'timestamp'         => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ride.no_drivers_available';
    }
}
