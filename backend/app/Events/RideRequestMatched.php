<?php

namespace App\Events;

use App\Models\Ride;
use App\Models\RideRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideRequestMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RideRequest $rideRequest,
        public Ride $ride,
        public int $estimatedPickupMinutes = 5
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->ride->rider_id}"),
            new PrivateChannel("user.{$this->ride->driver_id}"),
            new PrivateChannel("ride.{$this->ride->id}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ride_request_id' => $this->rideRequest->id,
            'ride_id' => $this->ride->id,
            'rider_id' => $this->ride->rider_id,
            'rider_name' => $this->ride->rider->name,
            'driver_id' => $this->ride->driver_id,
            'driver_name' => $this->ride->driver->name,
            'pickup_location' => [
                'id' => $this->ride->pickupLocation->id,
                'name' => $this->ride->pickupLocation->name,
                'coordinates' => [
                    'latitude' => $this->ride->pickupLocation->coordinates->latitude,
                    'longitude' => $this->ride->pickupLocation->coordinates->longitude,
                ],
            ],
            'destination_location' => [
                'id' => $this->ride->destinationLocation->id,
                'name' => $this->ride->destinationLocation->name,
                'coordinates' => [
                    'latitude' => $this->ride->destinationLocation->coordinates->latitude,
                    'longitude' => $this->ride->destinationLocation->coordinates->longitude,
                ],
            ],
            'estimated_fare_rp' => $this->ride->estimated_fare_rp,
            'passenger_count' => $this->ride->passenger_count,
            'estimated_pickup_minutes' => $this->estimatedPickupMinutes,
            'status' => $this->ride->status,
            'matched_at' => $this->ride->created_at->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ride.request.matched';
    }
}
