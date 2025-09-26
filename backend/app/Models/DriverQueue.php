<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DriverQueue model for Anjem ride-sharing platform
 *
 * Manages driver queues at beacon locations with Redis caching for real-time updates
 */
class DriverQueue extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'beacon_id',
        'position',
        'status',
        'joined_at',
        'left_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the driver in the queue.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the driver profile.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id', 'user_id');
    }

    /**
     * Get the beacon location.
     */
    public function beacon(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'beacon_id');
    }

    /**
     * Scope to get only waiting drivers
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope to get only active drivers (in queue)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['waiting', 'called']);
    }

    /**
     * Scope to get queue for a specific beacon
     */
    public function scopeAtBeacon($query, int $beaconId)
    {
        return $query->where('beacon_id', $beaconId);
    }

    /**
     * Scope to get queue for a specific driver
     */
    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope to order by position in queue
     */
    public function scopeOrderByPosition($query)
    {
        return $query->orderBy('position');
    }

    /**
     * Check if driver is currently waiting in queue
     */
    public function isWaiting(): bool
    {
        return $this->status === 'waiting' && !$this->left_at;
    }

    /**
     * Check if driver has been called for a ride
     */
    public function isCalled(): bool
    {
        return $this->status === 'called';
    }

    /**
     * Mark driver as called for a ride
     */
    public function markAsCalled(): void
    {
        $this->update(['status' => 'called']);
        $this->updateBeaconQueuePositions();
    }

    /**
     * Mark driver as served (got a ride)
     */
    public function markAsServed(): void
    {
        $this->update([
            'status' => 'served',
            'left_at' => now(),
        ]);
        $this->updateBeaconQueuePositions();
    }

    /**
     * Mark driver as left the queue
     */
    public function markAsLeft(): void
    {
        $this->update([
            'status' => 'left',
            'left_at' => now(),
        ]);
        $this->updateBeaconQueuePositions();
    }

    /**
     * Update queue positions after a driver leaves
     */
    protected function updateBeaconQueuePositions(): void
    {
        $waitingDrivers = static::atBeacon($this->beacon_id)
            ->waiting()
            ->orderBy('joined_at')
            ->get();

        foreach ($waitingDrivers as $index => $queueEntry) {
            $queueEntry->update(['position' => $index + 1]);
        }

        // Update beacon's current queue size
        $this->beacon->updateQueueSize();
    }

    /**
     * Get the estimated wait time in minutes
     */
    public function getEstimatedWaitTimeMinutes(): int
    {
        // Base assumption: each driver ahead takes 8-12 minutes on average
        $averageRideTime = 10; // minutes
        $driversAhead = max(0, $this->position - 1);

        return $driversAhead * $averageRideTime;
    }

    /**
     * Join driver to queue at beacon
     */
    public static function joinQueue(int $driverId, int $beaconId): ?self
    {
        $beacon = Location::find($beaconId);
        if (!$beacon || !$beacon->isBeacon() || !$beacon->hasCapacity()) {
            return null;
        }

        // Check if driver is already in a queue
        $existingEntry = static::forDriver($driverId)
            ->active()
            ->first();

        if ($existingEntry) {
            return null; // Driver already in a queue
        }

        // Get next position in queue
        $nextPosition = static::atBeacon($beaconId)
            ->waiting()
            ->count() + 1;

        $queueEntry = static::create([
            'driver_id' => $driverId,
            'beacon_id' => $beaconId,
            'position' => $nextPosition,
            'status' => 'waiting',
            'joined_at' => now(),
        ]);

        // Update beacon queue size
        $beacon->updateQueueSize();

        return $queueEntry;
    }

    /**
     * Get the next driver in queue at a beacon
     */
    public static function getNextDriverAtBeacon(int $beaconId): ?self
    {
        return static::atBeacon($beaconId)
            ->waiting()
            ->orderByPosition()
            ->first();
    }

    /**
     * Get current queue status at a beacon
     */
    public static function getQueueStatusAtBeacon(int $beaconId): array
    {
        $waitingDrivers = static::atBeacon($beaconId)
            ->waiting()
            ->with(['driver', 'driverProfile'])
            ->orderByPosition()
            ->get();

        return [
            'beacon_id' => $beaconId,
            'total_waiting' => $waitingDrivers->count(),
            'drivers' => $waitingDrivers->map(function ($entry) {
                return [
                    'driver_id' => $entry->driver_id,
                    'driver_name' => $entry->driver->name,
                    'position' => $entry->position,
                    'wait_time_minutes' => now()->diffInMinutes($entry->joined_at),
                    'estimated_wait_minutes' => $entry->getEstimatedWaitTimeMinutes(),
                ];
            }),
        ];
    }

    /**
     * Clean up old queue entries (left or served more than 24 hours ago)
     */
    public static function cleanupOldEntries(): void
    {
        static::whereIn('status', ['left', 'served'])
            ->where('left_at', '<', now()->subDay())
            ->delete();
    }
}