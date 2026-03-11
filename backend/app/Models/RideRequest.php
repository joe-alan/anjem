<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * RideRequest model for Anjem ride-sharing platform
 *
 * Represents a ride request made by a rider, before it's matched
 * with a driver and becomes an actual Ride.
 */
class RideRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rider_id',
        'pickup_location_id',
        'destination_location_id',
        'estimated_distance_km',
        'estimated_duration_minutes',
        'estimated_fare_rp',
        'passenger_count',
        'special_requests',
        'status',
        'expires_at',
        'matched_at',
        'current_driver_id',
        'rider_cooldown_until',
        'route_geometry',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'estimated_distance_km' => 'decimal:2',
        'estimated_duration_minutes' => 'integer',
        'estimated_fare_rp' => 'integer',
        'passenger_count' => 'integer',
        'special_requests' => 'json',
        'expires_at' => 'datetime',
        'matched_at' => 'datetime',
        'rider_cooldown_until' => 'datetime',
        'current_driver_id' => 'integer',
        'route_geometry' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the rider who made this request.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function currentDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_driver_id');
    }

    /**
     * Get the pickup location.
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    /**
     * Get the destination location.
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    /**
     * Get the actual ride if this request was matched.
     */
    public function ride(): HasOne
    {
        return $this->hasOne(Ride::class);
    }

    /**
     * Scope to get only pending ride requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get only matched ride requests
     */
    public function scopeMatched($query)
    {
        return $query->where('status', 'matched');
    }

    /**
     * Scope to get only expired ride requests
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope to get only cancelled ride requests
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get non-expired ride requests
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get ride requests from a specific beacon
     */
    public function scopeFromBeacon($query, int $beaconId)
    {
        return $query->where('pickup_location_id', $beaconId);
    }

    /**
     * Check if the ride request is still active (pending and not expired)
     */
    public function isActive(): bool
    {
        return $this->status === 'pending' && $this->expires_at > now();
    }

    /**
     * Check if the ride request is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Mark the ride request as matched
     */
    public function markAsMatched(): void
    {
        $this->update([
            'status'            => 'matched',
            'matched_at'        => now(),
            'current_driver_id' => null,  // Prevent stale timeout job from re-dispatching
        ]);
    }

    /**
     * Mark the ride request as actively in progress
     */
    public function markAsInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
            // Ensure matched_at has a value so analytics stay intact
            'matched_at' => $this->matched_at ?? now(),
        ]);
    }

    /**
     * Mark the ride request as completed
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark the ride request as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Mark the ride request as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Calculate priority score for matching (higher = higher priority)
     */
    public function calculatePriority(): int
    {
        $priority = 100; // Base priority

        // Prioritize based on wait time
        $waitTimeMinutes = now()->diffInMinutes($this->created_at);
        $priority += min($waitTimeMinutes * 2, 60); // Max 60 points for waiting

        // Prioritize based on passenger count (more passengers = higher priority)
        $priority += $this->passenger_count * 10;

        // Prioritize based on estimated fare (higher fare = higher priority)
        $priority += min($this->estimated_fare_rp / 1000, 50); // Max 50 points for fare

        return $priority;
    }

    /**
     * Get the estimated pickup time based on current conditions
     */
    public function getEstimatedPickupTime(): \Carbon\Carbon
    {
        // Base pickup time assumption: 5-15 minutes depending on queue
        $baseMinutes = 8;

        // Adjust based on beacon queue size
        if ($this->pickupLocation && $this->pickupLocation->isBeacon()) {
            $queueSize = $this->pickupLocation->current_queue_size;
            $additionalMinutes = max(0, ($queueSize - 3) * 2); // 2 min per driver above 3
            $baseMinutes += $additionalMinutes;
        }

        return now()->addMinutes($baseMinutes);
    }

    /**
     * Extend expiration time
     */
    public function extendExpiration(int $minutes = 10): void
    {
        $this->update(['expires_at' => $this->expires_at->addMinutes($minutes)]);
    }
}
