<?php

namespace Tests\Unit\Pages;

use App\Filament\Pages\SystemHealthPage;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class SystemHealthPageTest extends TestCase
{
    use RefreshDatabase;

    private SystemHealthPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = new SystemHealthPage;
    }

    // -------------------------------------------------------------------------
    // getApplicationMetrics
    // -------------------------------------------------------------------------

    public function test_metrics_counts_pending_ride_requests(): void
    {
        $this->createRideRequest(['status' => 'pending']);
        $this->createRideRequest(['status' => 'pending']);
        $this->createRideRequest(['status' => 'completed']); // excluded

        $metrics = $this->page->getApplicationMetrics();

        $this->assertEquals(2, $metrics['pending_requests']);
    }

    public function test_metrics_counts_matched_requests_as_pending(): void
    {
        $this->createRideRequest(['status' => 'matched']);

        $metrics = $this->page->getApplicationMetrics();

        $this->assertEquals(1, $metrics['pending_requests']);
    }

    public function test_metrics_counts_active_rides(): void
    {
        $this->createRide(['status' => 'accepted']);
        $this->createRide(['status' => 'in_progress']);
        $this->createRide(['status' => 'completed']); // excluded

        $metrics = $this->page->getApplicationMetrics();

        $this->assertEquals(2, $metrics['active_rides']);
    }

    public function test_metrics_counts_all_active_ride_statuses(): void
    {
        foreach (['matched', 'accepted', 'driver_arrived', 'in_progress'] as $status) {
            $this->createRide(['status' => $status]);
        }

        $metrics = $this->page->getApplicationMetrics();

        $this->assertEquals(4, $metrics['active_rides']);
    }

    public function test_metrics_counts_online_drivers(): void
    {
        $this->createDriverProfile(['went_online_at' => now()]);
        $this->createDriverProfile(['went_online_at' => now()]);
        $this->createDriverProfile(['went_online_at' => null]); // offline

        $metrics = $this->page->getApplicationMetrics();

        $this->assertEquals(2, $metrics['online_drivers']);
    }

    public function test_metrics_returns_zeros_when_no_data(): void
    {
        $metrics = $this->page->getApplicationMetrics();

        $this->assertEquals(0, $metrics['pending_requests']);
        $this->assertEquals(0, $metrics['active_rides']);
        $this->assertEquals(0, $metrics['online_drivers']);
    }

    // -------------------------------------------------------------------------
    // getReverbChannels
    // -------------------------------------------------------------------------

    public function test_reverb_channels_returns_parsed_channels_on_success(): void
    {
        Http::fake([
            '*' => Http::response(['channels' => [
                'private-admin.live' => ['subscription_count' => 1],
                'private-user.5' => ['subscription_count' => 2],
            ]], 200),
        ]);

        $channels = $this->page->getReverbChannels();

        $this->assertCount(2, $channels);
        $this->assertArrayHasKey('private-admin.live', $channels);
    }

    public function test_reverb_channels_returns_empty_on_http_failure(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $channels = $this->page->getReverbChannels();

        $this->assertEmpty($channels);
    }

    public function test_reverb_channels_returns_empty_on_connection_exception(): void
    {
        Http::fake(['*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('refused');
        }]);

        $channels = $this->page->getReverbChannels();

        $this->assertEmpty($channels);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createRideRequest(array $overrides = []): RideRequest
    {
        $rider = User::factory()->rider()->create();
        $pickup = $this->createLocation();
        $dest = $this->createLocation();

        return RideRequest::create(array_merge([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ], $overrides));
    }

    private function createRide(array $overrides = []): Ride
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $pickup = $this->createLocation();
        $dest = $this->createLocation();

        $request = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'matched',
            'expires_at' => now()->addMinutes(30),
        ]);

        return Ride::create(array_merge([
            'ride_request_id' => $request->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'passenger_count' => 1,
            'estimated_fare_rp' => 8000,
            'status' => 'accepted',
        ], $overrides));
    }

    private function createDriverProfile(array $overrides = []): DriverProfile
    {
        $user = User::factory()->driver()->create();

        return DriverProfile::create(array_merge([
            'user_id' => $user->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B' . rand(1000, 9999) . 'XYZ',
            'is_verified' => true,
            'credits_balance' => 0,
            'credits_total_earned' => 0,
            'credits_total_spent' => 0,
            'current_location' => new Point(-6.3605, 106.8271, 4326),
        ], $overrides));
    }

    private function createLocation(): Location
    {
        return Location::create([
            'name' => 'Test Location',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);
    }
}
