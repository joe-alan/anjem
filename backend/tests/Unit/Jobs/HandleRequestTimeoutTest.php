<?php

namespace Tests\Unit\Jobs;

use App\Jobs\HandleRequestTimeout;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\MatchingQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class HandleRequestTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $matchingQueueServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Bus::fake();

        $this->matchingQueueServiceMock = Mockery::mock(MatchingQueueService::class);
        $this->app->instance(MatchingQueueService::class, $this->matchingQueueServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // test_job_does_nothing_when_request_not_found
    // -------------------------------------------------------------------------

    public function test_job_does_nothing_when_request_not_found(): void
    {
        $this->expectNotToPerformAssertions();

        $nonExistentId = 999999;
        $driverId = 1;

        $this->matchingQueueServiceMock
            ->shouldNotReceive('handleDeclineOrTimeout');

        $job = new HandleRequestTimeout($nonExistentId, $driverId);
        $job->handle($this->matchingQueueServiceMock);
    }

    // -------------------------------------------------------------------------
    // test_job_does_nothing_when_request_is_already_in_progress
    // -------------------------------------------------------------------------

    public function test_job_does_nothing_when_request_is_already_in_progress(): void
    {
        $this->expectNotToPerformAssertions();

        $driver = $this->createDriver();
        $rideRequest = $this->createRideRequest([
            'status' => 'in_progress',
            'current_driver_id' => $driver->id,
        ]);

        $this->matchingQueueServiceMock
            ->shouldNotReceive('handleDeclineOrTimeout');

        $job = new HandleRequestTimeout($rideRequest->id, $driver->id);
        $job->handle($this->matchingQueueServiceMock);
    }

    // -------------------------------------------------------------------------
    // test_job_does_nothing_when_request_already_completed
    // -------------------------------------------------------------------------

    public function test_job_does_nothing_when_request_already_completed(): void
    {
        $this->expectNotToPerformAssertions();

        $driver = $this->createDriver();
        $rideRequest = $this->createRideRequest([
            'status' => 'completed',
            'current_driver_id' => $driver->id,
        ]);

        $this->matchingQueueServiceMock
            ->shouldNotReceive('handleDeclineOrTimeout');

        $job = new HandleRequestTimeout($rideRequest->id, $driver->id);
        $job->handle($this->matchingQueueServiceMock);
    }

    // -------------------------------------------------------------------------
    // test_job_does_nothing_when_request_already_cancelled
    // -------------------------------------------------------------------------

    public function test_job_does_nothing_when_request_already_cancelled(): void
    {
        $this->expectNotToPerformAssertions();

        $driver = $this->createDriver();
        $rideRequest = $this->createRideRequest([
            'status' => 'cancelled',
            'current_driver_id' => $driver->id,
        ]);

        $this->matchingQueueServiceMock
            ->shouldNotReceive('handleDeclineOrTimeout');

        $job = new HandleRequestTimeout($rideRequest->id, $driver->id);
        $job->handle($this->matchingQueueServiceMock);
    }

    // -------------------------------------------------------------------------
    // test_job_does_nothing_when_current_driver_changed
    // -------------------------------------------------------------------------

    public function test_job_does_nothing_when_current_driver_changed(): void
    {
        $this->expectNotToPerformAssertions();

        $originalDriver = $this->createDriver();
        $newDriver = $this->createDriver();

        // The request has already been reassigned to newDriver
        $rideRequest = $this->createRideRequest([
            'status' => 'pending',
            'current_driver_id' => $newDriver->id,
        ]);

        $this->matchingQueueServiceMock
            ->shouldNotReceive('handleDeclineOrTimeout');

        // Job was dispatched with the original driver's ID
        $job = new HandleRequestTimeout($rideRequest->id, $originalDriver->id);
        $job->handle($this->matchingQueueServiceMock);
    }

    // -------------------------------------------------------------------------
    // test_job_calls_handle_decline_when_request_is_pending
    // -------------------------------------------------------------------------

    public function test_job_calls_handle_decline_when_request_is_pending(): void
    {
        $driver = $this->createDriver();
        $rideRequest = $this->createRideRequest([
            'status' => 'pending',
            'current_driver_id' => $driver->id,
        ]);

        $this->matchingQueueServiceMock
            ->shouldReceive('handleDeclineOrTimeout')
            ->once()
            ->with($driver->id, $rideRequest->id);

        $job = new HandleRequestTimeout($rideRequest->id, $driver->id);
        $job->handle($this->matchingQueueServiceMock);

        // Mockery verifies the expectation in tearDown; count it as an assertion here
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // test_job_calls_handle_decline_when_request_is_matched
    // -------------------------------------------------------------------------

    public function test_job_calls_handle_decline_when_request_is_matched(): void
    {
        $driver = $this->createDriver();
        $rideRequest = $this->createRideRequest([
            'status' => 'matched',
            'current_driver_id' => $driver->id,
        ]);

        $this->matchingQueueServiceMock
            ->shouldReceive('handleDeclineOrTimeout')
            ->once()
            ->with($driver->id, $rideRequest->id);

        $job = new HandleRequestTimeout($rideRequest->id, $driver->id);
        $job->handle($this->matchingQueueServiceMock);

        // Mockery verifies the expectation in tearDown; count it as an assertion here
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create a driver User and an associated DriverProfile.
     */
    private function createDriver(array $userOverrides = [], array $profileOverrides = []): User
    {
        $user = User::factory()->driver()->create(array_merge([
            'is_active' => true,
        ], $userOverrides));

        DriverProfile::create(array_merge([
            'user_id' => $user->id,
            'is_verified' => true,
            'went_online_at' => now()->subHour(),
            'queue_joined_at' => now()->subMinutes(30),
            'max_pickup_radius_km' => 5.00,
            'decline_count' => 0,
            'total_rides_given' => 10,
        ], $profileOverrides));

        return $user;
    }

    /**
     * Create a RideRequest with the given attributes.
     * Automatically creates the required rider, pickup, and destination records.
     */
    private function createRideRequest(array $overrides = []): RideRequest
    {
        $rider = User::factory()->rider()->create();
        $pickup = $this->createLocation(true);
        $destination = $this->createLocation(false);

        // RideRequest::$fillable does not include current_driver_id, so we use
        // DB::table or forceFill to set it.
        $rideRequest = new RideRequest(array_merge([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 2.50,
            'estimated_duration_minutes' => 8,
            'estimated_fare_rp' => 10000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]));

        // Use forceFill so we can set current_driver_id even though it may not
        // be in $fillable on older versions of the model.
        $rideRequest->forceFill(array_filter([
            'current_driver_id' => $overrides['current_driver_id'] ?? null,
        ]));

        // Merge in remaining overrides (status, expires_at, etc.) via forceFill
        unset($overrides['current_driver_id']);
        if (! empty($overrides)) {
            $rideRequest->forceFill($overrides);
        }

        $rideRequest->save();

        return $rideRequest;
    }

    /**
     * Create a Location record (beacon or plain destination).
     */
    private function createLocation(bool $isBeacon): Location
    {
        return Location::create([
            'name' => $isBeacon ? 'Test Beacon' : 'Test Destination',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => $isBeacon,
            'is_active' => true,
        ]);
    }
}
