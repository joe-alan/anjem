<?php

namespace App\Events;

use App\Models\Location;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueuePositionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $driver,
        public Location $beacon,
        public int $position,
        public int $totalDrivers,
        public int $estimatedWaitMinutes,
        public string $action // 'joined', 'moved', 'left'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->driver->id}"),
            new PrivateChannel("driver.{$this->driver->id}"),
            new Channel("beacon.{$this->beacon->id}"), // Public channel for beacon status
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driver->id,
            'driver_name' => $this->driver->name,
            'beacon_id' => $this->beacon->id,
            'beacon_name' => $this->beacon->name,
            'position' => $this->position,
            'total_drivers' => $this->totalDrivers,
            'estimated_wait_minutes' => $this->estimatedWaitMinutes,
            'action' => $this->action,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'queue.position.changed';
    }

    /**
     * Customize the data for specific channels.
     */
    public function broadcastToChannel(Channel $channel): array
    {
        // For public beacon channel, don't include driver personal info
        if ($channel instanceof Channel && str_starts_with($channel->name, 'beacon.')) {
            return [
                'beacon_id' => $this->beacon->id,
                'beacon_name' => $this->beacon->name,
                'total_drivers' => $this->totalDrivers,
                'average_wait_minutes' => $this->estimatedWaitMinutes,
                'last_updated' => now()->toISOString(),
            ];
        }

        return $this->broadcastWith();
    }
}