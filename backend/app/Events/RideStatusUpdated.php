<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ride $ride,
        public string $previousStatus,
        public ?string $updatedBy = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ride.{$this->ride->id}"),
            new PrivateChannel("user.{$this->ride->rider_id}"),
            new PrivateChannel("user.{$this->ride->driver_id}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'status' => $this->ride->status,
            'previous_status' => $this->previousStatus,
            'updated_by' => $this->updatedBy,
            'rider_id' => $this->ride->rider_id,
            'driver_id' => $this->ride->driver_id,
            'pickup_location' => $this->ride->pickupLocation->name ?? null,
            'destination_location' => $this->ride->destinationLocation->name ?? null,
            'estimated_fare_rp' => $this->ride->estimated_fare_rp,
            'passenger_count' => $this->ride->passenger_count,
            'updated_at' => $this->ride->updated_at->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ride.status.updated';
    }
}