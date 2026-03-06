<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverKycStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $driver,
        public bool $isVerified,
        public ?string $reason = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("driver.{$this->driver->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id'   => $this->driver->id,
            'is_verified' => $this->isVerified,
            'reason'      => $this->reason,
            'timestamp'   => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'driver.kyc.updated';
    }
}
