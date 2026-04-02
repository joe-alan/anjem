<?php

namespace Tests\Feature\Api;

use App\Events\AdminLiveUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLiveUpdateEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcasts_on_private_admin_live_channel(): void
    {
        $event = new AdminLiveUpdate('ride_status', ['ride_id' => 42]);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('private-admin.live', $channels[0]->name);
    }

    public function test_broadcast_name_is_admin_live_update(): void
    {
        $event = new AdminLiveUpdate('ride_status', []);

        $this->assertEquals('admin.live.update', $event->broadcastAs());
    }

    public function test_broadcast_payload_includes_type_payload_and_timestamp(): void
    {
        $event = new AdminLiveUpdate('new_request', ['rider_id' => 7]);

        $data = $event->broadcastWith();

        $this->assertEquals('new_request', $data['type']);
        $this->assertEquals(['rider_id' => 7], $data['payload']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function test_broadcast_payload_timestamp_is_iso_string(): void
    {
        $event = new AdminLiveUpdate('driver_status', []);

        $data = $event->broadcastWith();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $data['timestamp']);
    }

    public function test_type_and_payload_are_public_properties(): void
    {
        $event = new AdminLiveUpdate('kyc_updated', ['driver_id' => 3]);

        $this->assertEquals('kyc_updated', $event->type);
        $this->assertEquals(['driver_id' => 3], $event->payload);
    }
}
