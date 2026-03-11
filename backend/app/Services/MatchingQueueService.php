<?php

namespace App\Services;

use App\Events\NewRideRequest;
use App\Jobs\HandleRequestTimeout;
use App\Models\DriverProfile;
use App\Models\RideRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * MatchingQueueService manages the global FIFO driver matching queue.
 *
 * Drivers join this queue when they go online (ordered by join time).
 * When a rider creates a request, the longest-waiting eligible driver
 * within the driver's max pickup radius is dispatched the request.
 * If they decline or timeout, the next driver is tried.
 *
 * NOTE: This is distinct from QueueService, which manages beacon-level
 * physical queue positions for drivers at pickup spots.
 */
class MatchingQueueService
{
    // Decline penalty thresholds
    private const PENALTY_WINDOW_MINUTES = 15;

    private const DECLINE_WARN_THRESHOLD = 3;

    private const DECLINE_BOTTOM_THRESHOLD = 5;

    private const DECLINE_COOLDOWN_THRESHOLD = 6;

    private const COOLDOWN_MINUTES = 15;

    // Rider cooldown after cancel/expire
    private const RIDER_COOLDOWN_SECONDS = 60;

    // Safety-net timeout job delay (slightly longer than the mobile 30s UI timer)
    private const TIMEOUT_JOB_DELAY_SECONDS = 35;

    /**
     * Add a driver to the FIFO matching queue.
     * Called when driver goes online.
     */
    public function addToQueue(int $driverId): void
    {
        DriverProfile::where('user_id', $driverId)->update([
            'queue_joined_at' => now(),
        ]);

        Log::info('Driver added to matching queue', ['driver_id' => $driverId]);
    }

    /**
     * Remove a driver from the FIFO matching queue.
     * Called when driver goes offline or is suspended.
     */
    public function removeFromQueue(int $driverId): void
    {
        DriverProfile::where('user_id', $driverId)->update([
            'queue_joined_at' => null,
        ]);

        Log::info('Driver removed from matching queue', ['driver_id' => $driverId]);
    }

    /**
     * Rejoin the queue after completing a ride.
     * Uses a fresh timestamp so the driver goes to the back of the queue.
     */
    public function rejoinAfterRide(int $driverId): void
    {
        DriverProfile::where('user_id', $driverId)->update([
            'queue_joined_at' => now(),
        ]);

        Log::info('Driver rejoined matching queue after ride completion', ['driver_id' => $driverId]);
    }

    /**
     * Find the top eligible driver for a ride request.
     *
     * Eligibility criteria:
     * - In the FIFO queue (queue_joined_at IS NOT NULL)
     * - Not in a decline penalty cooldown
     * - Verified and currently online
     * - No active ride
     * - Pickup location is within their max_pickup_radius_km
     *
     * Returns the longest-waiting eligible driver (queue_joined_at ASC).
     */
    public function findTopDriver(RideRequest $rideRequest): ?DriverProfile
    {
        $rideRequest->loadMissing('pickupLocation');
        $pickup = $rideRequest->pickupLocation;

        if (! $pickup || ! $pickup->coordinates) {
            Log::warning('Cannot find top driver: pickup location missing coordinates', [
                'ride_request_id' => $rideRequest->id,
            ]);

            return null;
        }

        $pickupLat = $pickup->coordinates->latitude;
        $pickupLng = $pickup->coordinates->longitude;

        // Build excluded driver IDs (drivers who have already been tried for this request)
        $excludedDriverIds = $this->getTriedDriverIds($rideRequest);

        $driver = DriverProfile::inQueue()
            ->notInCooldown()
            ->where('is_verified', true)
            ->whereNotNull('went_online_at')
            ->whereNotIn('user_id', $excludedDriverIds)
            ->whereDoesntHave('user', function ($q) {
                $q->whereHas('driverRides', function ($rq) {
                    $rq->whereIn('status', ['accepted', 'driver_arrived', 'in_progress']);
                });
            })
            ->whereRaw(
                'ST_Distance(
                    current_location::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) <= max_pickup_radius_km * 1000',
                [$pickupLng, $pickupLat]
            )
            ->orderBy('queue_joined_at', 'asc')
            ->first();

        return $driver;
    }

    /**
     * Dispatch a ride request to a specific driver.
     * Sets current_driver_id on the request and broadcasts the event.
     * Also schedules a safety-net timeout job.
     */
    public function dispatchToDriver(RideRequest $rideRequest, DriverProfile $driver): void
    {
        $rideRequest->update(['current_driver_id' => $driver->user_id]);

        // Record this dispatch attempt
        $this->recordDispatchAttempt($rideRequest, $driver->user_id);

        broadcast(new NewRideRequest($rideRequest, [$driver->user_id]));

        // Safety-net: if driver doesn't respond within 35s, auto-handle timeout
        HandleRequestTimeout::dispatch($rideRequest->id, $driver->user_id)
            ->delay(now()->addSeconds(self::TIMEOUT_JOB_DELAY_SECONDS));

        Log::info('Dispatched ride request to driver', [
            'ride_request_id' => $rideRequest->id,
            'driver_id' => $driver->user_id,
            'queue_position_was' => $this->getQueuePosition($driver->user_id),
        ]);
    }

    /**
     * Handle a driver decline or timeout.
     * Applies a penalty, then tries the next eligible driver.
     * If no driver is found, the request expires and the rider is notified.
     */
    public function handleDeclineOrTimeout(int $driverId, int $rideRequestId): void
    {
        $rideRequest = RideRequest::find($rideRequestId);

        if (! $rideRequest) {
            return;
        }

        // Only process if this driver is still the current driver (idempotency)
        if ($rideRequest->current_driver_id !== $driverId) {
            return;
        }

        // Only process pending requests
        if (! in_array($rideRequest->status, ['pending', 'matched'])) {
            return;
        }

        DB::transaction(function () use ($driverId, $rideRequest) {
            $this->applyDeclinePenalty($driverId);

            $rideRequest->update(['current_driver_id' => null]);

            // Try next driver
            $nextDriver = $this->findTopDriver($rideRequest);

            if ($nextDriver) {
                $this->dispatchToDriver($rideRequest, $nextDriver);

                Log::info('Passed ride request to next driver', [
                    'ride_request_id' => $rideRequest->id,
                    'declined_driver_id' => $driverId,
                    'next_driver_id' => $nextDriver->user_id,
                ]);
            } else {
                // No eligible drivers remain — expire the request
                $rideRequest->update([
                    'status' => 'expired',
                    'rider_cooldown_until' => now()->addSeconds(self::RIDER_COOLDOWN_SECONDS),
                ]);

                $this->notifyRiderNoDrivers($rideRequest);

                Log::info('No eligible drivers found, ride request expired', [
                    'ride_request_id' => $rideRequest->id,
                ]);
            }
        });
    }

    /**
     * Apply progressive decline penalty to a driver.
     */
    public function applyDeclinePenalty(int $driverId): void
    {
        $profile = DriverProfile::where('user_id', $driverId)->first();
        if (! $profile) {
            return;
        }

        // Reset window if it's older than PENALTY_WINDOW_MINUTES
        $windowExpired = $profile->decline_window_start === null
            || $profile->decline_window_start->addMinutes(self::PENALTY_WINDOW_MINUTES)->isPast();

        if ($windowExpired) {
            $profile->update([
                'decline_count' => 1,
                'decline_window_start' => now(),
            ]);

            return;
        }

        $newCount = $profile->decline_count + 1;

        $updates = ['decline_count' => $newCount];

        if ($newCount >= self::DECLINE_COOLDOWN_THRESHOLD) {
            // Temporary suspension from queue
            $updates['queue_cooldown_until'] = now()->addMinutes(self::COOLDOWN_MINUTES);
            $updates['queue_joined_at'] = null;

            Log::warning('Driver suspended from queue due to excessive declines', [
                'driver_id' => $driverId,
                'decline_count' => $newCount,
                'cooldown_until' => $updates['queue_cooldown_until'],
            ]);
        } elseif ($newCount >= self::DECLINE_BOTTOM_THRESHOLD) {
            // Move to bottom of queue
            $updates['queue_joined_at'] = now();
        }

        $profile->update($updates);
    }

    /**
     * Get a driver's current position in the FIFO queue (1-indexed).
     * Returns 0 if the driver is not in the queue.
     */
    public function getQueuePosition(int $driverId): int
    {
        $profile = DriverProfile::where('user_id', $driverId)->first();

        if (! $profile || ! $profile->isInQueue() || $profile->isInCooldown()) {
            return 0;
        }

        $position = DriverProfile::inQueue()
            ->notInCooldown()
            ->where('queue_joined_at', '<', $profile->queue_joined_at)
            ->count();

        return $position + 1;
    }

    /**
     * Set the rider cooldown after they cancel or their request expires.
     */
    public function applyRiderCooldown(RideRequest $rideRequest): void
    {
        $rideRequest->update([
            'rider_cooldown_until' => now()->addSeconds(self::RIDER_COOLDOWN_SECONDS),
        ]);
    }

    /**
     * Check if a rider is in cooldown (cannot create a new request yet).
     * Returns the cooldown timestamp or null.
     */
    public function getRiderCooldown(int $riderId): ?string
    {
        $cooldown = RideRequest::where('rider_id', $riderId)
            ->where('rider_cooldown_until', '>', now())
            ->orderBy('rider_cooldown_until', 'desc')
            ->value('rider_cooldown_until');

        return $cooldown ? $cooldown->toISOString() : null;
    }

    /**
     * Cleanup stale pending requests where current_driver_id is set but
     * the request has expired (fallback for missed timeout jobs).
     */
    public function cleanupTimedOutRequests(): void
    {
        $stale = RideRequest::where('status', 'pending')
            ->whereNotNull('current_driver_id')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $request) {
            $request->update([
                'status' => 'expired',
                'current_driver_id' => null,
            ]);

            $this->notifyRiderNoDrivers($request);
        }

        if ($stale->count() > 0) {
            Log::info('Cleaned up stale timed-out ride requests', ['count' => $stale->count()]);
        }
    }

    /**
     * Track which drivers have already been tried for a ride request.
     * Uses a Redis key to store the list.
     */
    private function recordDispatchAttempt(RideRequest $rideRequest, int $driverId): void
    {
        $key = "ride_request_tried_drivers:{$rideRequest->id}";
        $existing = cache()->get($key, []);
        $existing[] = $driverId;
        cache()->put($key, $existing, now()->addHour());
    }

    private function getTriedDriverIds(RideRequest $rideRequest): array
    {
        return cache()->get("ride_request_tried_drivers:{$rideRequest->id}", []);
    }

    private function notifyRiderNoDrivers(RideRequest $rideRequest): void
    {
        try {
            $rideRequest->loadMissing('rider');
            if ($rideRequest->rider) {
                app(NotificationService::class)->sendRideRequestTimeoutToRider($rideRequest);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send no-drivers notification to rider', [
                'ride_request_id' => $rideRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
