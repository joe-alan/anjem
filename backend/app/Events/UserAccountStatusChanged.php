<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAccountStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public bool $isSuspended,
        public ?string $reason = null,
    ) {}

    public function broadcastOn(): array
    {
        // Broadcast to both channels — the driver app subscribes to private-driver.{id}
        // and the rider app subscribes to private-user.{id}. A user with both roles
        // (or a test account) would only receive it on one channel otherwise.
        return [
            new PrivateChannel("driver.{$this->user->id}"),
            new PrivateChannel("user.{$this->user->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'      => $this->user->id,
            'is_suspended' => $this->isSuspended,
            'reason'       => $this->reason,
            'timestamp'    => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'account.status.changed';
    }
}
