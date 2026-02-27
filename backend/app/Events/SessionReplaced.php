<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to a driver's channel when a new login replaces an existing session.
 * The old device listens for this event and automatically signs out.
 */
class SessionReplaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(private int $driverId) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("driver.{$this->driverId}");
    }

    public function broadcastAs(): string
    {
        return 'session.replaced';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
