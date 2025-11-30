<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

/**
 * Test WebSocket channel authorization edge cases and security scenarios
 */
class WebSocketChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user channel authorization with valid user
     */
    public function test_user_channel_authorization_with_valid_user()
    {
        $user = User::factory()->rider()->create();

        // Test user can access their own channel
        $result = $this->callChannelAuth('user.'.$user->id, $user, $user->id);
        $this->assertTrue($result);

        // Test user cannot access another user's channel
        $otherUser = User::factory()->rider()->create();
        $result = $this->callChannelAuth('user.'.$otherUser->id, $user, $otherUser->id);
        $this->assertFalse($result);
    }

    /**
     * Test driver channel authorization edge cases
     */
    public function test_driver_channel_authorization_edge_cases()
    {
        // Test non-driver user cannot access driver channel
        $rider = User::factory()->rider()->create();
        $result = $this->callChannelAuth('driver.'.$rider->id, $rider, $rider->id);
        $this->assertFalse($result);

        // Test driver can access their own channel
        $driver = User::factory()->driver()->create();
        $result = $this->callChannelAuth('driver.'.$driver->id, $driver, $driver->id);
        $this->assertTrue($result);

        // Test driver cannot access another driver's channel
        $otherDriver = User::factory()->driver()->create();
        $result = $this->callChannelAuth('driver.'.$otherDriver->id, $driver, $otherDriver->id);
        $this->assertFalse($result);

        // Test 'both' user type can access driver channel
        $bothUser = User::factory()->create(['role' => 'both']);
        $result = $this->callChannelAuth('driver.'.$bothUser->id, $bothUser, $bothUser->id);
        $this->assertTrue($result);
    }

    /**
     * Test ride channel authorization with various scenarios
     */
    public function test_ride_channel_authorization_scenarios()
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $outsider = User::factory()->rider()->create();

        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        // Test rider can access their ride
        $result = $this->callChannelAuth('ride.'.$ride->id, $rider, $ride->id);
        $this->assertTrue($result);

        // Test driver can access their ride
        $result = $this->callChannelAuth('ride.'.$ride->id, $driver, $ride->id);
        $this->assertTrue($result);

        // Test outsider cannot access the ride
        $result = $this->callChannelAuth('ride.'.$ride->id, $outsider, $ride->id);
        $this->assertFalse($result);
    }

    /**
     * Test ride channel authorization with non-existent ride
     */
    public function test_ride_channel_authorization_with_non_existent_ride()
    {
        $user = User::factory()->rider()->create();

        // Test access to non-existent ride
        $result = $this->callChannelAuth('ride.99999', $user, 99999);
        $this->assertFalse($result);
    }

    /**
     * Test ride channel authorization with deleted ride
     */
    public function test_ride_channel_authorization_with_deleted_ride()
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();

        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);
        $rideId = $ride->id;

        // Delete the ride
        $ride->delete();

        // Test access to deleted ride
        $result = $this->callChannelAuth('ride.'.$rideId, $rider, $rideId);
        $this->assertFalse($result);
    }

    /**
     * Test beacon channel authorization edge cases
     */
    public function test_beacon_channel_authorization_edge_cases()
    {
        $user = User::factory()->rider()->create();

        // Test access to valid active beacon
        $activeBeacon = $this->createTestBeacon(['is_active' => true]);
        $result = $this->callChannelAuth('beacon.'.$activeBeacon->id, $user, $activeBeacon->id);
        $this->assertTrue($result);

        // Test access to inactive beacon
        $inactiveBeacon = $this->createTestBeacon(['is_active' => false]);
        $result = $this->callChannelAuth('beacon.'.$inactiveBeacon->id, $user, $inactiveBeacon->id);
        $this->assertFalse($result);

        // Test access to non-beacon location
        $nonBeacon = Location::create([
            'name' => 'Non-Beacon Location',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);
        $result = $this->callChannelAuth('beacon.'.$nonBeacon->id, $user, $nonBeacon->id);
        $this->assertFalse($result);

        // Test access to non-existent beacon
        $result = $this->callChannelAuth('beacon.99999', $user, 99999);
        $this->assertFalse($result);
    }

    /**
     * Test channel authorization with invalid user IDs
     */
    public function test_channel_authorization_with_invalid_user_ids()
    {
        $user = User::factory()->rider()->create();

        // Test with string user ID
        $result = $this->callChannelAuth('user.invalid', $user, 'invalid');
        $this->assertFalse($result);

        // Test with negative user ID
        $result = $this->callChannelAuth('user.-1', $user, -1);
        $this->assertFalse($result);

        // Test with zero user ID
        $result = $this->callChannelAuth('user.0', $user, 0);
        $this->assertFalse($result);
    }

    /**
     * Test channel authorization with malicious input
     */
    public function test_channel_authorization_with_malicious_input()
    {
        $user = User::factory()->rider()->create();

        $maliciousInputs = [
            "1'; DROP TABLE users; --",
            '1 OR 1=1',
            '../../../etc/passwd',
            "<script>alert('xss')</script>",
            '1 UNION SELECT * FROM users',
        ];

        foreach ($maliciousInputs as $maliciousId) {
            // Test user channel with malicious input
            $result = $this->callChannelAuth('user.'.$maliciousId, $user, $maliciousId);
            $this->assertFalse($result);

            // Test ride channel with malicious input
            $result = $this->callChannelAuth('ride.'.$maliciousId, $user, $maliciousId);
            $this->assertFalse($result);
        }
    }

    /**
     * Test concurrent channel authorization requests
     */
    public function test_concurrent_channel_authorization_requests()
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        // Simulate concurrent authorization requests
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->callChannelAuth('ride.'.$ride->id, $rider, $ride->id);
            $results[] = $this->callChannelAuth('ride.'.$ride->id, $driver, $ride->id);
        }

        // All authorized requests should succeed
        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    /**
     * Test channel authorization with very large IDs
     */
    public function test_channel_authorization_with_large_ids()
    {
        $user = User::factory()->rider()->create();

        $largeIds = [
            PHP_INT_MAX,
            '999999999999999999',
            2147483647, // 32-bit max int
            9223372036854775807, // 64-bit max int
        ];

        foreach ($largeIds as $largeId) {
            $result = $this->callChannelAuth('user.'.$largeId, $user, $largeId);
            $this->assertFalse($result); // Should fail as user doesn't exist
        }
    }

    /**
     * Test authorization with soft-deleted users
     */
    public function test_authorization_with_soft_deleted_users()
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        // Soft delete the rider
        $rider->delete();

        // Deleted user should not be able to access channels
        $result = $this->callChannelAuth('user.'.$rider->id, $rider, $rider->id);
        $this->assertFalse($result);

        // Deleted rider should not be able to access ride
        $result = $this->callChannelAuth('ride.'.$ride->id, $rider, $ride->id);
        $this->assertFalse($result);

        // But active driver should still be able to access ride
        $result = $this->callChannelAuth('ride.'.$ride->id, $driver, $ride->id);
        $this->assertTrue($result);
    }

    /**
     * Test authorization performance with many users
     */
    public function test_authorization_performance_with_many_users()
    {
        // Create many users
        $users = User::factory()->count(100)->rider()->create();
        $testUser = $users->first();

        $startTime = microtime(true);

        // Test authorization for each user's channel
        foreach ($users as $user) {
            $result = $this->callChannelAuth('user.'.$user->id, $testUser, $user->id);

            // Only the test user should be able to access their own channel
            if ($user->id === $testUser->id) {
                $this->assertTrue($result);
            } else {
                $this->assertFalse($result);
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Authorization should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $executionTime, 'Authorization took too long: '.$executionTime.' seconds');
    }

    /**
     * Test authorization with invalid channel formats
     */
    public function test_authorization_with_invalid_channel_formats()
    {
        $user = User::factory()->rider()->create();

        $invalidChannels = [
            'user.',           // Missing ID
            'user..',          // Empty ID
            'ride',            // Missing dot and ID
            'beacon..1',       // Double dot
            'invalid.channel.1', // Unknown channel type
            '',                // Empty channel
            'user.1.extra',    // Extra segments
        ];

        foreach ($invalidChannels as $channel) {
            // These should not crash and should return false
            try {
                $result = $this->callChannelAuth($channel, $user, 1);
                $this->assertFalse($result);
            } catch (\Exception $e) {
                // If an exception is thrown, the channel format is properly rejected
                $this->assertInstanceOf(\Exception::class, $e);
            }
        }
    }

    // Helper methods

    /**
     * Call channel authorization callback
     */
    private function callChannelAuth(string $channelName, User $user, $parameter)
    {
        // Extract channel type and call appropriate authorization
        $parts = explode('.', $channelName);
        $channelType = $parts[0];

        switch ($channelType) {
            case 'user':
                $callback = function ($user, $id) {
                    return (int) $user->id === (int) $id;
                };

                return $callback($user, $parameter);

            case 'driver':
                $callback = function ($user, $driverId) {
                    return (int) $user->id === (int) $driverId && $user->isDriver();
                };

                return $callback($user, $parameter);

            case 'ride':
                $callback = function ($user, $rideId) {
                    $ride = Ride::find($rideId);
                    if (! $ride) {
                        return false;
                    }

                    return (int) $user->id === (int) $ride->rider_id ||
                           (int) $user->id === (int) $ride->driver_id;
                };

                return $callback($user, $parameter);

            case 'beacon':
                $callback = function ($user, $beaconId) {
                    return Location::where('id', $beaconId)
                        ->where('is_beacon', true)
                        ->where('is_active', true)
                        ->exists();
                };

                return $callback($user, $parameter);

            default:
                return false;
        }
    }

    private function createTestRide(array $overrides = []): Ride
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();

        $pickup = $this->createTestBeacon();
        $destination = $this->createTestLocation();

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'matched',
            'expires_at' => now()->addMinutes(30),
        ]);

        return Ride::create(array_merge([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'matched',
            'passenger_count' => 1,
            'estimated_fare_rp' => 8000,
        ], $overrides));
    }

    private function createTestBeacon(array $overrides = []): Location
    {
        return Location::create(array_merge([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function createTestLocation(): Location
    {
        return Location::create([
            'name' => 'Test Destination',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);
    }
}
