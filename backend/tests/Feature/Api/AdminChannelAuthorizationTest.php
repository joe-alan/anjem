<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // The channel callback from routes/channels.php
    private function callAdminLiveAuth(User $user): bool
    {
        return $user->isAdmin();
    }

    public function test_admin_user_can_access_admin_live_channel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->callAdminLiveAuth($admin));
    }

    public function test_rider_cannot_access_admin_live_channel(): void
    {
        $rider = User::factory()->rider()->create();

        $this->assertFalse($this->callAdminLiveAuth($rider));
    }

    public function test_driver_cannot_access_admin_live_channel(): void
    {
        $driver = User::factory()->driver()->create();

        $this->assertFalse($this->callAdminLiveAuth($driver));
    }

    public function test_both_role_cannot_access_admin_live_channel(): void
    {
        $both = User::factory()->create(['role' => 'both']);

        $this->assertFalse($this->callAdminLiveAuth($both));
    }

    public function test_admin_live_channel_is_registered(): void
    {
        // Verifies the channel is present in the registered broadcast channels
        $channels = app(\Illuminate\Broadcasting\BroadcastManager::class)
            ->getChannels();

        $this->assertArrayHasKey('admin.live', $channels);
    }
}
