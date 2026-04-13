<?php

namespace Tests\Feature\Api;

use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\RouteCache;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rider;
    private User $driver;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
        ]);

        // Create admin token with proper abilities
        $this->adminToken = $this->admin->createTokenWithAbilities(false, true);

        // Create test rider
        $this->rider = User::factory()->create([
            'email' => 'rider@test.com',
            'role' => 'rider',
            'is_active' => true,
        ]);

        // Create test driver with profile
        $this->driver = User::factory()->create([
            'email' => 'driver@test.com',
            'role' => 'driver',
            'is_active' => true,
        ]);

        DriverProfile::create([
            'user_id' => $this->driver->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B1234XYZ',
            'is_verified' => true,
            'email_verified_at' => Carbon::now(),
        ]);
    }

    /**
     * Test non-admin user cannot access admin endpoints
     */
    public function test_non_admin_cannot_access_admin_endpoints()
    {
        $riderToken = $this->rider->createTokenWithAbilities(true, false);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$riderToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/analytics/overview');

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access admin endpoints
     */
    public function test_unauthenticated_user_cannot_access_admin_endpoints()
    {
        $response = $this->getJson('/api/admin/analytics/overview');

        $response->assertStatus(401);
    }

    /**
     * Test admin can access overview analytics
     */
    public function test_admin_can_access_overview_analytics()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/analytics/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_users',
                    'total_riders',
                    'total_drivers',
                    'online_drivers',
                    'active_rides',
                    'total_rides',
                    'completed_rides',
                    'total_revenue',
                    'route_cache',
                ],
            ]);
    }

    /**
     * Test admin can list drivers
     */
    public function test_admin_can_list_drivers()
    {
        // Create additional drivers
        $driver2 = User::factory()->create(['role' => 'driver']);
        DriverProfile::create([
            'user_id' => $driver2->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B5678ABC',
            'is_verified' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/drivers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Test admin can filter drivers by KYC status
     */
    public function test_admin_can_filter_drivers_by_kyc_status()
    {
        // Create driver with approved KYC
        $approvedDriver = User::factory()->create(['role' => 'driver']);
        DriverProfile::create([
            'user_id' => $approvedDriver->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B9999XYZ',
            'is_verified' => true,
            'email_verified_at' => Carbon::now(),
        ]);

        // Filter for approved drivers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/drivers?kyc_status=approved');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data')); // Original driver + approved driver
    }

    /**
     * Test admin can search drivers by name or email
     */
    public function test_admin_can_search_drivers()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/drivers?search=driver@test.com');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    /**
     * Test admin can view driver details
     */
    public function test_admin_can_view_driver_details()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/drivers/'.$this->driver->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'role',
                    'is_active',
                    'stats',
                ],
            ]);

        // Verify it contains driver-specific data
        $this->assertArrayHasKey('total_rides', $response->json('data.stats'));
    }

    /**
     * Test admin can suspend driver
     */
    public function test_admin_can_suspend_driver()
    {
        $this->assertTrue($this->driver->is_active);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/suspend', [
            'suspended' => true,
            'reason' => 'Violating terms of service',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Driver suspended successfully',
            ]);

        $this->driver->refresh();
        $this->assertFalse($this->driver->is_active);
    }

    /**
     * Test admin can unsuspend driver
     */
    public function test_admin_can_unsuspend_driver()
    {
        // First suspend the driver
        $this->driver->update(['is_active' => false]);
        $this->assertFalse($this->driver->is_active);

        // Then unsuspend
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/suspend', [
            'suspended' => false,
        ]);

        $response->assertStatus(200);

        $this->driver->refresh();
        $this->assertTrue($this->driver->is_active);
    }

    /**
     * Test admin can list riders
     */
    public function test_admin_can_list_riders()
    {
        // Create additional riders
        User::factory()->count(3)->create(['role' => 'rider']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/riders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);

        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    /**
     * Test admin can view rider details
     */
    public function test_admin_can_view_rider_details()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/riders/'.$this->rider->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'is_active',
                    'stats',
                ],
            ]);

        // Verify rider stats exist
        $this->assertArrayHasKey('total_rides', $response->json('data.stats'));
    }

    /**
     * Test admin can suspend rider
     */
    public function test_admin_can_suspend_rider()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/riders/'.$this->rider->id.'/suspend', [
            'suspended' => true,
            'reason' => 'Abusive behavior',
        ]);

        $response->assertStatus(200);

        $this->rider->refresh();
        $this->assertFalse($this->rider->is_active);
    }

    /**
     * Test admin can view ride analytics
     */
    public function test_admin_can_view_ride_analytics()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/analytics/rides');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        // Just verify we get data back
        $this->assertIsArray($response->json('data'));
    }

    /**
     * Test admin can view popular routes
     */
    public function test_admin_can_view_popular_routes()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Library',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Engineering',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create cached route
        RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'profile' => 'driving',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 10,
            'last_fetched_at' => Carbon::now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/analytics/popular-routes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cached_routes',
                    'actual_rides',
                ],
            ]);
    }

    /**
     * Test admin can view driver performance
     */
    public function test_admin_can_view_driver_performance()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/analytics/driver-performance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can view active rides
     */
    public function test_admin_can_view_active_rides()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/monitoring/active-rides');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can view online drivers
     */
    public function test_admin_can_view_online_drivers()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/monitoring/online-drivers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can view pending ride requests
     */
    public function test_admin_can_view_pending_requests()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/monitoring/pending-requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can view specific ride details
     */
    public function test_admin_can_view_ride_details()
    {
        // Create test locations
        $pickup = Location::create([
            'name' => 'Pickup',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create ride request
        $rideRequest = RideRequest::create([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'passenger_count' => 1,
            'estimated_distance_meters' => 270,
            'estimated_duration_minutes' => 2,
            'estimated_price' => 5000,
            'status' => 'completed',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Create ride
        $ride = Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $this->rider->id,
            'driver_id' => $this->driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'completed',
            'estimated_price' => 5000,
            'final_price' => 5000,
            'distance_meters' => 270,
            'duration_minutes' => 2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/rides/'.$ride->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        // Verify ride data is returned
        $this->assertEquals($ride->id, $response->json('data.id'));
    }

    /**
     * Test admin can force update ride status
     */
    public function test_admin_can_force_update_ride_status()
    {
        // Create test locations
        $pickup = Location::create([
            'name' => 'Pickup',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create ride request
        $rideRequest = RideRequest::create([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'passenger_count' => 1,
            'estimated_distance_meters' => 270,
            'estimated_duration_minutes' => 2,
            'estimated_price' => 5000,
            'status' => 'matched',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Create stuck ride
        $ride = Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $this->rider->id,
            'driver_id' => $this->driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'in_progress',
            'estimated_price' => 5000,
            'distance_meters' => 270,
            'duration_minutes' => 2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/rides/'.$ride->id.'/force-status', [
            'status' => 'completed',
            'reason' => 'Manually completing stuck ride',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride status updated successfully',
            ]);

        $ride->refresh();
        $this->assertEquals('completed', $ride->status);
    }

    /**
     * Test admin can view stuck rides
     */
    public function test_admin_can_view_stuck_rides()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/rides/stuck');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin token has correct abilities
     */
    public function test_admin_token_has_correct_abilities()
    {
        $token = $this->admin->tokens()->where('name', 'admin-token')->first();

        $this->assertNotNull($token);

        // Check for admin abilities
        $abilities = $token->abilities;
        $this->assertContains('admin:view-users', $abilities);
        $this->assertContains('admin:manage-users', $abilities);
        $this->assertContains('admin:view-rides', $abilities);
        $this->assertContains('admin:manage-rides', $abilities);
    }
}
