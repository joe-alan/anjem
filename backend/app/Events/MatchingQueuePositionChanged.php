<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchingQueuePositionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $driverId,
        public int $queuePosition,
        public bool $isInCooldown,
        public ?string $cooldownUntil,
        public float $maxPickupRadiusKm
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver.{$this->driverId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'queue_position' => $this->queuePosition,
            'is_in_cooldown' => $this->isInCooldown,
            'cooldown_until' => $this->cooldownUntil,
            'max_pickup_radius_km' => $this->maxPickupRadiusKm,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'queue.position.changed';
    }
}
