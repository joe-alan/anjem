<?php

namespace Tests\Unit\Services;

use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Messaging\CloudMessage;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

/**
 * Tests for NotificationService FCM integration
 *
 * These tests mock Firebase Cloud Messaging to test notification logic
 * and error handling without requiring actual FCM credentials.
 */
class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;
    private $mockMessaging;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Firebase Messaging
        $this->mockMessaging = Mockery::mock(Messaging::class);
        $this->notificationService = new NotificationService($this->mockMessaging);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test successful ride matched notification to rider
     */
    public function test_send_ride_matched_to_rider_success()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'valid_fcm_token_123',
            'is_active' => true,
        ]);

        $driver = User::factory()->driver()->create([
            'name' => 'John Driver',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        // Mock successful FCM send
        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendRideMatchedToRider($ride);

        $this->assertTrue($result);
    }

    /**
     * Test ride matched notification fails when rider has no FCM token
     */
    public function test_send_ride_matched_fails_without_fcm_token()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => null,
            'is_active' => true,
        ]);

        $ride = $this->createTestRide(['rider_id' => $rider->id]);

        // Should not attempt to send
        $this->mockMessaging->shouldNotReceive('send');

        $result = $this->notificationService->sendRideMatchedToRider($ride);

        $this->assertFalse($result);
    }

    /**
     * Test FCM exception handling
     */
    public function test_handles_fcm_exceptions_gracefully()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'invalid_token',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide(['rider_id' => $rider->id]);

        // Mock FCM exception
        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andThrow(new InvalidMessage('Invalid registration token'));

        $result = $this->notificationService->sendRideMatchedToRider($ride);

        $this->assertFalse($result);
    }

    /**
     * Test ride request notification to driver
     */
    public function test_send_ride_request_to_driver()
    {
        $driver = User::factory()->driver()->create([
            'fcm_token' => 'driver_token_123',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide([
            'driver_id' => $driver->id,
            'estimated_fare_rp' => 15000,
            'passenger_count' => 2,
        ]);

        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendRideRequestToDriver($ride);

        $this->assertTrue($result);
    }

    /**
     * Test ride accepted notification
     */
    public function test_send_ride_accepted_to_rider()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'rider_token_456',
            'is_active' => true,
        ]);

        $driver = User::factory()->driver()->create([
            'name' => 'Jane Driver',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
        ]);

        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendRideAcceptedToRider($ride);

        $this->assertTrue($result);
    }

    /**
     * Test driver arrived notification
     */
    public function test_send_driver_arrived_to_rider()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'rider_token_789',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide(['rider_id' => $rider->id]);

        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendDriverArrivedToRider($ride);

        $this->assertTrue($result);
    }

    /**
     * Test ride started notification
     */
    public function test_send_ride_started_to_rider()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'rider_token_start',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide([
            'rider_id' => $rider->id,
            'status' => 'in_progress',
        ]);

        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendRideStartedToRider($ride);

        $this->assertTrue($result);
    }

    /**
     * Test ride completed notifications to both parties
     */
    public function test_send_ride_completed_notifications()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'rider_token_complete',
            'is_active' => true,
        ]);

        $driver = User::factory()->driver()->create([
            'fcm_token' => 'driver_token_complete',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'actual_fare_rp' => 12000,
        ]);

        // Should send to both rider and driver
        $this->mockMessaging
            ->shouldReceive('send')
            ->twice()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $results = $this->notificationService->sendRideCompletedNotifications($ride);

        $this->assertTrue($results['rider']);
        $this->assertTrue($results['driver']);
    }

    /**
     * Test ride cancelled notification scenarios
     */
    public function test_send_ride_cancelled_notification_by_rider()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver = User::factory()->driver()->create([
            'fcm_token' => 'driver_token_cancel',
            'is_active' => true,
        ]);

        $ride = $this->createTestRide([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
        ]);

        // Only driver should receive notification when rider cancels
        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $results = $this->notificationService->sendRideCancelledNotification(
            $ride,
            $rider->id,
            'Change of plans'
        );

        $this->assertFalse($results['rider']);
        $this->assertTrue($results['driver']);
    }

    /**
     * Test ride cancelled notification by driver
     */
    public function test_send_ride_cancelled_notification_by_driver()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'rider_token_cancel',
            'is_active' => true,
        ]);
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $ride = $this->createTestRide([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
        ]);

        // Only rider should receive notification when driver cancels
        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $results = $this->notificationService->sendRideCancelledNotification(
            $ride,
            $driver->id,
            'Vehicle breakdown'
        );

        $this->assertTrue($results['rider']);
        $this->assertFalse($results['driver']);
    }

    /**
     * Test queue position update notification
     */
    public function test_send_queue_position_update()
    {
        $driver = User::factory()->driver()->create([
            'fcm_token' => 'driver_queue_token',
            'is_active' => true,
        ]);

        $queueStatus = [
            'position' => 3,
            'estimated_wait_minutes' => 15,
            'beacon_id' => 1,
        ];

        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendQueuePositionUpdate($driver, $queueStatus);

        $this->assertTrue($result);
    }

    /**
     * Test ride request timeout notification
     */
    public function test_send_ride_request_timeout_to_rider()
    {
        $rider = User::factory()->rider()->create([
            'fcm_token' => 'rider_timeout_token',
            'is_active' => true,
        ]);

        $rideRequest = $this->createTestRideRequest(['rider_id' => $rider->id]);

        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendRideRequestTimeoutToRider($rideRequest);

        $this->assertTrue($result);
    }

    /**
     * Test announcement to all users
     */
    public function test_send_announcement_to_all_users()
    {
        // Create users with FCM tokens
        User::factory()->count(3)->create([
            'fcm_token' => 'user_token_123',
            'is_active' => true,
        ]);

        // Create inactive user (should be excluded)
        User::factory()->create([
            'fcm_token' => 'inactive_token',
            'is_active' => false,
        ]);

        // Should send to 3 active users
        $this->mockMessaging
            ->shouldReceive('send')
            ->times(3)
            ->andReturn(['name' => 'projects/test/messages/123']);

        $successCount = $this->notificationService->sendAnnouncementToAllUsers(
            'System Maintenance',
            'The app will be under maintenance from 2-4 AM tonight.'
        );

        $this->assertEquals(3, $successCount);
    }

    /**
     * Test sending notification to specific user
     */
    public function test_send_to_user()
    {
        $user = User::factory()->create([
            'fcm_token' => 'specific_user_token',
            'is_active' => true,
        ]);

        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendToUser(
            $user,
            'Personal Message',
            'Welcome to Anjem!',
            ['welcome' => 'true']
        );

        $this->assertTrue($result);
    }

    /**
     * Test updating user FCM token
     */
    public function test_update_user_token()
    {
        $user = User::factory()->create(['fcm_token' => 'old_token']);

        $result = $this->notificationService->updateUserToken($user->id, 'new_token_123');

        $this->assertTrue($result);

        $user->refresh();
        $this->assertEquals('new_token_123', $user->fcm_token);
    }

    /**
     * Test updating token for non-existent user
     */
    public function test_update_token_for_non_existent_user()
    {
        $result = $this->notificationService->updateUserToken(99999, 'some_token');

        $this->assertFalse($result);
    }

    /**
     * Test FCM token validation
     */
    public function test_fcm_token_validation()
    {
        // Valid token
        $validToken = str_repeat('a', 152); // FCM tokens are typically 152+ chars
        $this->assertTrue($this->notificationService->isValidToken($validToken));

        // Invalid tokens
        $this->assertFalse($this->notificationService->isValidToken('')); // Empty
        $this->assertFalse($this->notificationService->isValidToken('short')); // Too short
        $this->assertFalse($this->notificationService->isValidToken('invalid!@#$%')); // Invalid chars
    }

    /**
     * Test sending test notification
     */
    public function test_send_test_notification()
    {
        $this->mockMessaging
            ->shouldReceive('send')
            ->once()
            ->andReturn(['name' => 'projects/test/messages/123']);

        $result = $this->notificationService->sendTestNotification('test_token_123');

        $this->assertTrue($result);
    }

    /**
     * Test cleanup of invalid tokens
     */
    public function test_cleanup_invalid_tokens()
    {
        // Create users with old last_active_at dates
        User::factory()->count(2)->create([
            'fcm_token' => 'old_token',
            'last_active_at' => now()->subDays(35),
            'is_active' => true,
        ]);

        // Create recent active user (should not be cleaned)
        User::factory()->create([
            'fcm_token' => 'recent_token',
            'last_active_at' => now()->subDays(5),
            'is_active' => true,
        ]);

        $cleanedCount = $this->notificationService->cleanupInvalidTokens();

        $this->assertEquals(2, $cleanedCount);

        // Verify old tokens were cleared
        $usersWithNullTokens = User::whereNull('fcm_token')->count();
        $this->assertEquals(2, $usersWithNullTokens);
    }

    /**
     * Test edge case: notification with missing ride relationships
     */
    public function test_notification_with_missing_relationships()
    {
        $ride = $this->createTestRide();

        // Delete the rider to simulate missing relationship
        $ride->rider()->delete();
        $ride->refresh();

        $result = $this->notificationService->sendRideMatchedToRider($ride);

        $this->assertFalse($result);
    }

    /**
     * Test concurrent notification sending
     */
    public function test_concurrent_notifications_handling()
    {
        $users = User::factory()->count(5)->create([
            'fcm_token' => 'concurrent_token',
            'is_active' => true,
        ]);

        // Mock all sends to succeed
        $this->mockMessaging
            ->shouldReceive('send')
            ->times(5)
            ->andReturn(['name' => 'projects/test/messages/123']);

        $successCount = $this->notificationService->sendAnnouncementToAllUsers(
            'Urgent Update',
            'Please update your app to the latest version.'
        );

        $this->assertEquals(5, $successCount);
    }

    /**
     * Test partial notification failures in batch sending
     */
    public function test_partial_failure_in_batch_notifications()
    {
        User::factory()->count(3)->create([
            'fcm_token' => 'batch_token',
            'is_active' => true,
        ]);

        // Mock: first two succeed, third fails
        $this->mockMessaging
            ->shouldReceive('send')
            ->times(3)
            ->andReturnUsing(function() {
                static $callCount = 0;
                $callCount++;
                if ($callCount <= 2) {
                    return ['name' => 'projects/test/messages/' . $callCount];
                }
                throw new \Exception('FCM error');
            });

        $successCount = $this->notificationService->sendAnnouncementToAllUsers(
            'Batch Test',
            'Testing partial failures'
        );

        // Should still return count of successful sends
        $this->assertEquals(2, $successCount);
    }

    // Helper methods

    /**
     * Create a test ride with default relationships
     */
    private function createTestRide(array $overrides = []): Ride
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $pickup = Location::create([
            'name' => 'Test Pickup',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Test Destination',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

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

    /**
     * Create a test ride request
     */
    private function createTestRideRequest(array $overrides = []): RideRequest
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);

        $pickup = Location::create([
            'name' => 'Test Pickup Location',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Test Destination Location',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

        return RideRequest::create(array_merge([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ], $overrides));
    }
}