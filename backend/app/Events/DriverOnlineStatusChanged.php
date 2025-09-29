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

class DriverOnlineStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $driver,
        public bool $isOnline,
        public ?Location $beacon = null,
        public ?int $queuePosition = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("user.{$this->driver->id}"),
            new PrivateChannel("driver.{$this->driver->id}"),
        ];

        // If driver went online at a beacon, broadcast to beacon channel
        if ($this->isOnline && $this->beacon) {
            $channels[] = new Channel("beacon.{$this->beacon->id}");
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'driver_id' => $this->driver->id,
            'driver_name' => $this->driver->name,
            'is_online' => $this->isOnline,
            'status' => $this->isOnline ? 'online' : 'offline',
            'timestamp' => now()->toISOString(),
        ];

        if ($this->isOnline && $this->beacon) {
            $data['beacon'] = [
                'id' => $this->beacon->id,
                'name' => $this->beacon->name,
            ];
            $data['queue_position'] = $this->queuePosition;
        }

        return $data;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'driver.status.changed';
    }

    /**
     * Customize the data for specific channels.
     */
    public function broadcastToChannel(Channel $channel): array
    {
        // For public beacon channel, only include minimal driver info
        if ($channel instanceof Channel && str_starts_with($channel->name, 'beacon.')) {
            return [
                'beacon_id' => $this->beacon?->id,
                'drivers_online_count_changed' => $this->isOnline ? 1 : -1,
                'timestamp' => now()->toISOString(),
            ];
        }

        return $this->broadcastWith();
    }
}