<?php

namespace App\Services;

use App\Models\DriverQueue;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * QueueService manages driver queues at beacon locations.
 *
 * Uses Redis for real-time caching and PostgreSQL for persistence.
 * Provides queue management operations and real-time status updates.
 *
 * @deprecated Legacy from the fixed-pickup-point ("beacon") model, which was replaced by the
 * standard ride-hailing flow. {@see MatchingQueueService} is the active FIFO dispatcher. This
 * class is still injected into RideService for a single `markDriverServed()` call on ride
 * completion and referenced by a job, a console command, and three Filament pages. See
 * docs/TODO.md #15 for the removal plan.
 */
class QueueService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const QUEUE_KEY_PREFIX = 'beacon_queue:';

    private const DRIVER_STATUS_PREFIX = 'driver_status:';

    /**
     * Join a driver to a beacon queue
     */
    public function joinQueue(int $driverId, int $beaconId): ?DriverQueue
    {
        try {
            // Check if beacon exists and has capacity
            $beacon = Location::find($beaconId);
            if (! $beacon || ! $beacon->isBeacon() || ! $beacon->hasCapacity()) {
                return null;
            }

            // Check if driver can be a driver
            $driver = User::find($driverId);
            if (! $driver || ! $driver->canBeDriver() || ! $driver->is_active) {
                return null;
            }

            // Create queue entry
            $queueEntry = DriverQueue::joinQueue($driverId, $beaconId);

            if ($queueEntry) {
                // Update Redis cache
                $this->cacheQueueStatus($beaconId);
                $this->cacheDriverStatus($driverId, 'in_queue', $beaconId);

                Log::info('Driver joined queue', [
                    'driver_id' => $driverId,
                    'beacon_id' => $beaconId,
                    'position' => $queueEntry->position,
                ]);
            }

            return $queueEntry;

        } catch (\Exception $e) {
            Log::error('Failed to join queue', [
                'driver_id' => $driverId,
                'beacon_id' => $beaconId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Remove a driver from their current queue
     */
    public function leaveQueue(int $driverId): bool
    {
        try {
            $queueEntry = DriverQueue::forDriver($driverId)
                ->active()
                ->first();

            if (! $queueEntry) {
                return false; // Driver not in any queue
            }

            $beaconId = $queueEntry->beacon_id;
            $queueEntry->markAsLeft();

            // Update Redis cache
            $this->cacheQueueStatus($beaconId);
            $this->cacheDriverStatus($driverId, 'offline');

            Log::info('Driver left queue', [
                'driver_id' => $driverId,
                'beacon_id' => $beaconId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to leave queue', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get a driver's current queue position and status
     */
    public function getDriverQueueStatus(int $driverId): array
    {
        $cacheKey = self::DRIVER_STATUS_PREFIX.$driverId;

        // Try Redis cache first (skip in tests)
        if (! app()->environment('testing')) {
            $cached = Cache::get($cacheKey);
            if ($cached && isset($cached['beacon_name'])) {
                return $cached;
            }
        }

        // Fallback to database
        $queueEntry = DriverQueue::forDriver($driverId)
            ->active()
            ->with('beacon')
            ->first();

        $status = $queueEntry ? [
            'in_queue' => true,
            'beacon_id' => $queueEntry->beacon_id,
            'beacon_name' => $queueEntry->beacon?->name ?? 'Unknown Beacon',
            'position' => $queueEntry->position,
            'status' => $queueEntry->status,
            'wait_time_minutes' => now()->diffInMinutes($queueEntry->joined_at),
            'estimated_wait_minutes' => $queueEntry->getEstimatedWaitTimeMinutes(),
            'joined_at' => $queueEntry->joined_at->toISOString(),
        ] : [
            'in_queue' => false,
            'beacon_id' => null,
            'position' => null,
            'status' => 'offline',
            'wait_time_minutes' => 0,
            'estimated_wait_minutes' => 0,
        ];

        // Cache for future requests
        Cache::put($cacheKey, $status, self::CACHE_TTL);

        return $status;
    }

    /**
     * Get real-time queue status for a beacon
     */
    public function getBeaconQueueStatus(int $beaconId): array
    {
        $cacheKey = self::QUEUE_KEY_PREFIX.$beaconId;

        // Try Redis cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fallback to database
        $status = DriverQueue::getQueueStatusAtBeacon($beaconId);

        // Cache for future requests
        Cache::put($cacheKey, $status, self::CACHE_TTL);

        return $status;
    }

    /**
     * Get the next available driver at a beacon
     */
    public function getNextDriverAtBeacon(int $beaconId): ?DriverQueue
    {
        return DriverQueue::getNextDriverAtBeacon($beaconId);
    }

    /**
     * Mark a driver as called for a ride
     */
    public function callDriver(int $driverId): bool
    {
        try {
            $queueEntry = DriverQueue::forDriver($driverId)
                ->waiting()
                ->first();

            if (! $queueEntry) {
                return false;
            }

            $queueEntry->markAsCalled();

            // Update Redis cache
            $this->cacheQueueStatus($queueEntry->beacon_id);
            $this->cacheDriverStatus($driverId, 'called', $queueEntry->beacon_id);

            Log::info('Driver called for ride', [
                'driver_id' => $driverId,
                'beacon_id' => $queueEntry->beacon_id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to call driver', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark a driver as served (got a ride)
     */
    public function markDriverServed(int $driverId): bool
    {
        try {
            $queueEntry = DriverQueue::forDriver($driverId)
                ->whereIn('status', ['waiting', 'called'])
                ->first();

            if (! $queueEntry) {
                return false;
            }

            $beaconId = $queueEntry->beacon_id;
            $queueEntry->markAsServed();

            // Update Redis cache
            $this->cacheQueueStatus($beaconId);
            $this->cacheDriverStatus($driverId, 'in_ride');

            Log::info('Driver marked as served', [
                'driver_id' => $driverId,
                'beacon_id' => $beaconId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark driver as served', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get queue statistics for all beacons
     */
    public function getAllBeaconStatistics()
    {
        return Location::beacons()
            ->active()
            ->get()
            ->map(function ($beacon) {
                return [
                    'beacon_id' => $beacon->id,
                    'beacon_name' => $beacon->name,
                    'capacity' => $beacon->beacon_capacity,
                    'current_queue_size' => $beacon->current_queue_size,
                    'utilization_percent' => round(($beacon->current_queue_size / max($beacon->beacon_capacity, 1)) * 100, 1),
                    'has_capacity' => $beacon->hasCapacity(),
                    'coordinates' => $beacon->latLng,
                ];
            });
    }

    /**
     * Get drivers waiting across all beacons
     */
    public function getAllWaitingDrivers(): array
    {
        $drivers = DriverQueue::waiting()
            ->with(['driver', 'beacon'])
            ->orderBy('joined_at')
            ->get();

        return [
            'total_waiting' => $drivers->count(),
            'drivers' => $drivers->map(function ($entry) {
                return [
                    'driver_id' => $entry->driver_id,
                    'driver_name' => $entry->driver->name,
                    'beacon_id' => $entry->beacon_id,
                    'beacon_name' => $entry->beacon->name,
                    'position' => $entry->position,
                    'wait_time_minutes' => now()->diffInMinutes($entry->joined_at),
                    'estimated_wait_minutes' => $entry->getEstimatedWaitTimeMinutes(),
                ];
            }),
        ];
    }

    /**
     * Clean up old queue entries and refresh caches
     */
    public function performMaintenance(): void
    {
        try {
            // Clean up old entries
            DriverQueue::cleanupOldEntries();

            // Refresh all beacon queue caches
            $beacons = Location::beacons()->pluck('id');
            foreach ($beacons as $beaconId) {
                $this->refreshBeaconCache($beaconId);
            }

            // Clear stale driver status caches
            $this->clearStaleDriverCaches();

            Log::info('Queue maintenance completed');

        } catch (\Exception $e) {
            Log::error('Queue maintenance failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Cache queue status for a beacon
     */
    private function cacheQueueStatus(int $beaconId): void
    {
        $cacheKey = self::QUEUE_KEY_PREFIX.$beaconId;
        $status = DriverQueue::getQueueStatusAtBeacon($beaconId);
        Cache::put($cacheKey, $status, self::CACHE_TTL);
    }

    /**
     * Cache driver status
     */
    private function cacheDriverStatus(int $driverId, string $status, ?int $beaconId = null): void
    {
        // For complex status info, let the getDriverQueueStatus method handle it
        // and cache the complete result there
        $cacheKey = self::DRIVER_STATUS_PREFIX.$driverId;
        Cache::forget($cacheKey);
    }

    /**
     * Refresh cache for a specific beacon
     */
    private function refreshBeaconCache(int $beaconId): void
    {
        $cacheKey = self::QUEUE_KEY_PREFIX.$beaconId;
        Cache::forget($cacheKey);
        $this->cacheQueueStatus($beaconId);
    }

    /**
     * Clear stale driver status caches
     */
    private function clearStaleDriverCaches(): void
    {
        // For MVP, we'll rely on TTL expiration
        // In production, could implement more sophisticated cleanup
        // based on actual driver status or last activity
    }

    /**
     * Broadcast real-time updates for queue changes
     * Integration point for WebSocket/Reverb events
     */
    private function broadcastQueueUpdate(int $beaconId, array $queueData): void
    {
        // TODO: Implement in Phase 5 with Reverb WebSocket
        // broadcast(new QueueUpdatedEvent($beaconId, $queueData));
    }

    /**
     * Check if a driver can join a specific beacon queue
     */
    public function canDriverJoinBeacon(int $driverId, int $beaconId): array
    {
        $beacon = Location::find($beaconId);
        $driver = User::find($driverId);

        $reasons = [];

        if (! $beacon) {
            $reasons[] = 'Beacon not found';
        } else {
            if (! $beacon->isBeacon()) {
                $reasons[] = 'Location is not a beacon';
            }
            if (! $beacon->is_active) {
                $reasons[] = 'Beacon is not active';
            }
            if (! $beacon->hasCapacity()) {
                $reasons[] = 'Beacon queue is full';
            }
        }

        if (! $driver) {
            $reasons[] = 'Driver not found';
        } else {
            if (! $driver->canBeDriver()) {
                $reasons[] = 'User cannot be a driver';
            }
            if (! $driver->is_active) {
                $reasons[] = 'Driver account is not active';
            }
        }

        // Check if driver is already in a queue
        $existingQueue = DriverQueue::forDriver($driverId)->active()->first();
        if ($existingQueue) {
            $reasons[] = 'Driver already in queue at another beacon';
        }

        return [
            'can_join' => empty($reasons),
            'reasons' => $reasons,
        ];
    }
}
