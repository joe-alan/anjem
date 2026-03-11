<?php

namespace Tests\Unit\Services;

use App\Models\Location;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

/**
 * Tests for Phase 1 of mapbox_optimisation.md
 * Verifies route_geometry is stored on ride_requests at creation time
 */
class RideRequestRouteGeometryTest extends TestCase
{
    use RefreshDatabase;

    private Location $pickup;

    private Location $destination;

    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.mapbox.public_token' => 'pk.test_token']);

        $this->rider = User::factory()->rider()->create(['is_active' => true]);

        $this->pickup = Location::create([
            'name' => 'Library Gate',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $this->destination = Location::create([
            'name' => 'Engineering Faculty',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);
    }

    private function fakeMapboxDirections(array $geometry = null): void
    {
        $geometry ??= [
            'type' => 'LineString',
            'coordinates' => [[106.8271, -6.3605], [106.8283, -6.3600], [106.8295, -6.3595]],
        ];

        Http::fake([
            'api.mapbox.com/directions/v5/mapbox/driving-traffic/*' => Http::response([
                'routes' => [[
                    'distance' => 350,
                    'duration' => 90,
                    'geometry' => $geometry,
                ]],
            ], 200),
        ]);
    }

    public function test_create_ride_request_stores_route_geometry(): void
    {
        $this->fakeMapboxDirections();

        $rideService = app(\App\Services\RideService::class);

        $rideRequest = $rideService->createRideRequest([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $this->pickup->id,
            'destination_location_id' => $this->destination->id,
            'passenger_count' => 1,
        ]);

        $this->assertNotNull($rideRequest);
        $this->assertNotNull($rideRequest->route_geometry, 'route_geometry should be stored on ride_request');
        $this->assertEquals('LineString', $rideRequest->route_geometry['type']);
        $this->assertArrayHasKey('coordinates', $rideRequest->route_geometry);
    }

    public function test_route_geometry_is_null_when_mapbox_fails(): void
    {
        // Mapbox returns a 500 error — service falls back to straight-line estimate (no geometry)
        Http::fake([
            'api.mapbox.com/*' => Http::response(null, 500),
        ]);

        $rideService = app(\App\Services\RideService::class);

        $rideRequest = $rideService->createRideRequest([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $this->pickup->id,
            'destination_location_id' => $this->destination->id,
            'passenger_count' => 1,
        ]);

        $this->assertNotNull($rideRequest, 'Ride request should still be created on Mapbox failure');
        $this->assertNull($rideRequest->route_geometry, 'route_geometry should be null when Mapbox fails');
    }

    public function test_route_geometry_is_cast_to_array_not_string(): void
    {
        $this->fakeMapboxDirections();

        $rideService = app(\App\Services\RideService::class);

        $rideRequest = $rideService->createRideRequest([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $this->pickup->id,
            'destination_location_id' => $this->destination->id,
            'passenger_count' => 1,
        ]);

        $this->assertNotNull($rideRequest);
        $this->assertNotNull($rideRequest->route_geometry);
        $this->assertIsArray($rideRequest->route_geometry);
    }

    public function test_route_geometry_persists_correctly_in_database(): void
    {
        $expectedGeometry = [
            'type' => 'LineString',
            'coordinates' => [[106.8271, -6.3605], [106.8295, -6.3595]],
        ];

        $this->fakeMapboxDirections($expectedGeometry);

        $rideService = app(\App\Services\RideService::class);

        $rideRequest = $rideService->createRideRequest([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $this->pickup->id,
            'destination_location_id' => $this->destination->id,
            'passenger_count' => 1,
        ]);

        $this->assertNotNull($rideRequest);

        // Re-fetch from DB to confirm persistence
        $fresh = RideRequest::find($rideRequest->id);
        $this->assertEquals($expectedGeometry, $fresh->route_geometry);
    }

    public function test_create_ride_request_with_both_location_ids_populates_route_geometry(): void
    {
        $this->fakeMapboxDirections();

        $rideService = app(\App\Services\RideService::class);

        // Passing explicit IDs (not coordinates) is what triggers the cache-enabled path
        $rideRequest = $rideService->createRideRequest([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $this->pickup->id,
            'destination_location_id' => $this->destination->id,
            'passenger_count' => 2,
        ]);

        $this->assertNotNull($rideRequest);
        $this->assertNotNull($rideRequest->route_geometry);

        // Also verify other fields are correct
        $this->assertEquals($this->pickup->id, $rideRequest->pickup_location_id);
        $this->assertEquals($this->destination->id, $rideRequest->destination_location_id);
        $this->assertEquals(2, $rideRequest->passenger_count);
        $this->assertEquals('pending', $rideRequest->status);
    }
}
