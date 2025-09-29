<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $driver,
        public float $latitude,
        public float $longitude,
        public ?int $activeRideId = null,
        public ?float $heading = null,
        public ?float $speed = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("driver.{$this->driver->id}"),
        ];

        // If driver has an active ride, broadcast to the ride channel
        if ($this->activeRideId) {
            $channels[] = new PrivateChannel("ride.{$this->activeRideId}");
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driver->id,
            'driver_name' => $this->driver->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'heading' => $this->heading,
            'speed' => $this->speed,
            'active_ride_id' => $this->activeRideId,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    /**
     * Determine if this event should broadcast.
     * Only broadcast if driver is online or has an active ride.
     */
    public function shouldBroadcast(): bool
    {
        // Only broadcast if driver is online or has an active ride
        return $this->driver->isOnline() || $this->activeRideId !== null;
    }
}