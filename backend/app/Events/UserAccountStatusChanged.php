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
        // Ensure driverProfile is loaded without triggering an extra query if already present.
        $this->user->loadMissing('driverProfile');

        // Drivers subscribe to private-driver.{id} — always active when logged in.
        // Riders subscribe to private-user.{id} — active during ride request flow.
        if ($this->user->driverProfile) {
            return [new PrivateChannel("driver.{$this->user->id}")];
        }

        return [new PrivateChannel("user.{$this->user->id}")];
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
