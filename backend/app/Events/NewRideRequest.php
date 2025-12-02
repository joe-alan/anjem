<?php

namespace App\Events;

use App\Models\Location;
use App\Models\RideRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewRideRequest implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int>  $driverIds
     */
    public function __construct(
        public RideRequest $rideRequest,
        public array $driverIds
    ) {
        $this->rideRequest->loadMissing(['pickupLocation', 'destinationLocation', 'rider']);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $driverId) => new PrivateChannel("driver.{$driverId}"),
            $this->driverIds
        );
    }

    public function broadcastWith(): array
    {
        $pickup = $this->rideRequest->pickupLocation;
        $destination = $this->rideRequest->destinationLocation;

        return [
            'ride_request_id' => $this->rideRequest->id,
            'rider_id' => $this->rideRequest->rider_id,
            'rider_name' => $this->rideRequest->rider?->name,
            'passenger_count' => $this->rideRequest->passenger_count,
            'special_requests' => $this->rideRequest->special_requests,
            'estimated_fare_rp' => $this->rideRequest->estimated_fare_rp,
            'status' => $this->rideRequest->status,
            'pickup_location' => $pickup ? $this->transformLocation($pickup) : null,
            'destination_location' => $destination ? $this->transformLocation($destination) : null,
            'estimated_pickup_minutes' => $this->rideRequest->getEstimatedPickupTime()->diffInMinutes(now()),
            'expires_at' => $this->rideRequest->expires_at?->toISOString(),
            'created_at' => $this->rideRequest->created_at?->toISOString(),
            'updated_at' => $this->rideRequest->updated_at?->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ride.request.new';
    }

    private function transformLocation(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'description' => $location->description,
            'type' => $location->isBeacon() ? 'beacon' : 'p2p',
            'is_active' => $location->is_active,
            'queue_count' => $location->current_queue_size,
            'coordinates' => [
                'latitude' => $location->coordinates->latitude,
                'longitude' => $location->coordinates->longitude,
            ],
            'created_at' => $location->created_at?->toISOString() ?? now()->toISOString(),
            'updated_at' => $location->updated_at?->toISOString() ?? now()->toISOString(),
        ];
    }
}
