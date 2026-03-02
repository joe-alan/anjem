<?php

namespace App\Services;

use App\Events\MatchingQueuePositionChanged;
use App\Events\NewRideRequest;
use App\Events\RideNoDriversAvailable;
use App\Events\RideRequestCancelled;
use App\Jobs\ExpireRideRequest;
use App\Jobs\HandleRequestTimeout;
use App\Models\DriverProfile;
use App\Models\RideRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * MatchingQueueService manages the global FIFO driver matching queue.
 *
 * Drivers join this queue when they go online (ordered by join time).
 * When a rider creates a request, the system fetches the top CANDIDATE_POOL_SIZE
 * longest-waiting eligible drivers and dispatches to the one closest to the pickup.
 * Drivers within TIEBREAKER_DISTANCE_METERS are treated as equally close; among
 * them the longer-waiting driver wins. If they decline or timeout, the next driver
 * is tried using the same logic.
 *
 * NOTE: This is distinct from QueueService, which manages beacon-level
 * physical queue positions for drivers at pickup spots.
 */
class MatchingQueueService
{
    // Nearest-first candidate pool: FIFO selects the eligible pool, distance picks the winner.
    // Increase CANDIDATE_POOL_SIZE to consider more waiting drivers; decrease to bias toward FIFO.
    private const CANDIDATE_POOL_SIZE = 5;

    // Starvation guard: two drivers within this many meters are treated as "equally close".
    // The one who has been waiting longer wins ties inside a distance bucket.
    private const TIEBREAKER_DISTANCE_METERS = 300;

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

    // Countdown shown to rider when no drivers are available before expiry
    private const NO_DRIVERS_COUNTDOWN_SECONDS = 60;

    /**
     * Add a driver to the FIFO matching queue.
     * Called when driver goes online.
     */
    public function addToQueue(int $driverId): void
    {
        DriverProfile::where('user_id', $driverId)->update([
            'queue_joined_at' => now(),
        ]);

        $this->broadcastAllQueuePositions();

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

        // Tell the leaving driver their position is now 0
        $this->broadcastQueuePosition($driverId);
        // Update positions for everyone remaining in the queue
        $this->broadcastAllQueuePositions();

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

        $this->broadcastAllQueuePositions();

        Log::info('Driver rejoined matching queue after ride completion', ['driver_id' => $driverId]);
    }

    /**
     * Find the top eligible driver for a ride request.
     *
     * Eligibility criteria (unchanged):
     * - In the FIFO queue (queue_joined_at IS NOT NULL)
     * - Not in a decline penalty cooldown
     * - Verified and currently online
     * - No active ride
     * - Pickup location is within their max_pickup_radius_km
     *
     * Selection strategy (nearest-first within FIFO candidate pool):
     * 1. Fetch the CANDIDATE_POOL_SIZE longest-waiting eligible drivers (FIFO order).
     * 2. Among those candidates, pick the one closest to the rider's pickup point.
     * 3. Starvation guard: drivers within TIEBREAKER_DISTANCE_METERS of each other
     *    are treated as equally close — the longer-waiting one wins.
     *
     * This means a driver can only be skipped if up to (CANDIDATE_POOL_SIZE - 1) others
     * have waited longer AND are meaningfully closer, bounding worst-case wait time.
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

        // Drivers already tried for this request (declined or timed out)
        $excludedDriverIds = $this->getTriedDriverIds($rideRequest);

        // Step 1: Fetch the top CANDIDATE_POOL_SIZE longest-waiting eligible drivers.
        // All existing eligibility filters are preserved — only LIMIT + distance column added.
        $candidates = DriverProfile::inQueue()
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
            // Attach each candidate's distance to the pickup so we can sort in PHP.
            ->selectRaw(
                '*, ST_Distance(
                    current_location::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) AS distance_meters',
                [$pickupLng, $pickupLat]
            )
            ->orderBy('queue_joined_at', 'asc')
            ->limit(self::CANDIDATE_POOL_SIZE)
            ->get();

        // Step 2: No candidates — caller handles the no-drivers event.
        if ($candidates->isEmpty()) {
            return null;
        }

        // Step 3: Single candidate — pick them immediately, no sorting needed.
        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        // Step 4: Among the candidates, pick the closest driver.
        // Starvation guard: collapse distances into TIEBREAKER_DISTANCE_METERS buckets
        // so that a driver who barely joined cannot steal the spot from a long-waiting
        // driver who is within the same ~300 m band. Within a bucket, FIFO wins.
        return $candidates->sortBy([
            // Primary sort: 300 m distance bucket (lower = closer to rider)
            fn ($a, $b) => (int) ($a->distance_meters / self::TIEBREAKER_DISTANCE_METERS)
                       <=> (int) ($b->distance_meters / self::TIEBREAKER_DISTANCE_METERS),
            // Secondary sort: FIFO tiebreaker within the same distance bucket
            fn ($a, $b) => $a->queue_joined_at <=> $b->queue_joined_at,
        ])->first();
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

            // Exclude the declining/timed-out driver from the next dispatch attempt
            $this->recordDispatchAttempt($rideRequest, $driverId);

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
                // No eligible drivers remain — notify rider with a countdown,
                // then expire the request after the countdown elapses.
                broadcast(new RideNoDriversAvailable($rideRequest, self::NO_DRIVERS_COUNTDOWN_SECONDS));

                ExpireRideRequest::dispatch($rideRequest->id)
                    ->delay(now()->addSeconds(self::NO_DRIVERS_COUNTDOWN_SECONDS));

                Log::info('No eligible drivers found, started expiry countdown', [
                    'ride_request_id'    => $rideRequest->id,
                    'countdown_seconds'  => self::NO_DRIVERS_COUNTDOWN_SECONDS,
                ]);
            }
        });

        // Dismiss the previous driver's incoming-request screen.
        // current_driver_id is already cleared on the model, so pass $driverId explicitly.
        // This handles the timeout case where the driver never tapped Decline themselves.
        try {
            broadcast(new RideRequestCancelled($rideRequest, 'system', null, $driverId));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast dismiss event to previous driver', [
                'driver_id' => $driverId,
                'ride_request_id' => $rideRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
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

        $this->broadcastQueuePosition($driverId);
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

        return $cooldown ? Carbon::parse($cooldown)->toISOString() : null;
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

    /**
     * Broadcast updated positions to every driver currently in the queue.
     * Called whenever the queue composition changes so that all drivers see
     * the correct rank without needing to reload the app.
     */
    private function broadcastAllQueuePositions(): void
    {
        $driverIds = DriverProfile::inQueue()->notInCooldown()->pluck('user_id');
        foreach ($driverIds as $driverId) {
            $this->broadcastQueuePosition($driverId);
        }
    }

    /**
     * Broadcast the driver's current FIFO queue position to their private channel.
     * Called whenever the driver's position in the matching queue changes.
     */
    private function broadcastQueuePosition(int $driverId): void
    {
        $profile = DriverProfile::where('user_id', $driverId)->first();

        if (! $profile) {
            return;
        }

        $position = $this->getQueuePosition($driverId);

        try {
            broadcast(new MatchingQueuePositionChanged(
                $driverId,
                $position,
                $profile->isInCooldown(),
                $profile->queue_cooldown_until?->toISOString(),
                (float) $profile->max_pickup_radius_km
            ));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast queue position change', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
