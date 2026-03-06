<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverCreditsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $driver,
        public int $newBalance,
        public int $amount,
        public string $operation, // 'grant' or 'deduct'
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("driver.{$this->driver->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id'   => $this->driver->id,
            'new_balance' => $this->newBalance,
            'amount'      => $this->amount,
            'operation'   => $this->operation,
            'timestamp'   => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'driver.credits.updated';
    }
}
