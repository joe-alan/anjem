<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ride model for Anjem ride-sharing platform
 *
 * Represents an actual ride that has been matched between a rider and driver.
 * Created when a RideRequest is successfully matched with a driver.
 */
class Ride extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ride_request_id',
        'rider_id',
        'driver_id',
        'pickup_location_id',
        'destination_location_id',
        'status',
        'passenger_count',
        'estimated_fare_rp',
        'actual_fare_rp',
        'actual_distance_km',
        'actual_duration_minutes',
        'driver_accepted_at',
        'pickup_time',
        'dropoff_time',
        'special_requests',
        'driver_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'passenger_count' => 'integer',
        'estimated_fare_rp' => 'integer',
        'actual_fare_rp' => 'integer',
        'actual_distance_km' => 'decimal:2',
        'actual_duration_minutes' => 'integer',
        'driver_accepted_at' => 'datetime',
        'pickup_time' => 'datetime',
        'dropoff_time' => 'datetime',
        'special_requests' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the original ride request.
     */
    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    /**
     * Get the rider.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * Get the driver.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
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
     * Get ratings for this ride.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Scope to get rides by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get accepted rides
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope to get rides in progress
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope to get completed rides
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get cancelled rides
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get rides for a specific driver
     */
    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope to get rides for a specific rider
     */
    public function scopeForRider($query, int $riderId)
    {
        return $query->where('rider_id', $riderId);
    }

    /**
     * Check if ride is currently active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['accepted', 'in_progress']);
    }

    /**
     * Check if ride is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark ride as accepted by driver
     */
    public function markAsAccepted(): void
    {
        $this->update([
            'status' => 'accepted',
            'driver_accepted_at' => now(),
        ]);
    }

    /**
     * Mark ride as in progress (pickup completed)
     */
    public function startRide(): void
    {
        $this->update([
            'status' => 'in_progress',
            'pickup_time' => now(),
        ]);
    }

    /**
     * Mark ride as completed
     */
    public function completeRide(?float $actualDistance = null, ?int $actualFare = null): void
    {
        $updates = [
            'status' => 'completed',
            'dropoff_time' => now(),
        ];

        if ($actualDistance !== null) {
            $updates['actual_distance_km'] = $actualDistance;
        }

        if ($actualFare !== null) {
            $updates['actual_fare_rp'] = $actualFare;
        }

        // Calculate actual duration
        if ($this->pickup_time) {
            $updates['actual_duration_minutes'] = now()->diffInMinutes($this->pickup_time);
        }

        $this->update($updates);

        // Update driver's ride count
        if ($this->driver && $this->driver->driverProfile) {
            $this->driver->driverProfile->incrementRideCount();
        }
    }

    /**
     * Cancel the ride
     */
    public function cancelRide(string $reason = null): void
    {
        $updates = ['status' => 'cancelled'];

        if ($reason) {
            $updates['driver_notes'] = $reason;
        }

        $this->update($updates);
    }

    /**
     * Get the actual or estimated fare
     */
    public function getFinalFareAttribute(): int
    {
        return $this->actual_fare_rp ?? $this->estimated_fare_rp;
    }

    /**
     * Get the actual or estimated distance
     */
    public function getFinalDistanceAttribute(): float
    {
        return $this->actual_distance_km ?? $this->rideRequest->estimated_distance_km ?? 0;
    }

    /**
     * Get the total ride duration from acceptance to completion
     */
    public function getTotalDurationMinutesAttribute(): ?int
    {
        if (!$this->driver_accepted_at) {
            return null;
        }

        $endTime = $this->dropoff_time ?? now();
        return $this->driver_accepted_at->diffInMinutes($endTime);
    }

    /**
     * Check if the ride can be rated
     */
    public function canBeRated(): bool
    {
        return $this->isCompleted() && $this->dropoff_time->diffInHours(now()) <= 72; // Can rate within 72 hours
    }

    /**
     * Get rating given by rider to driver
     */
    public function getRiderToDriverRating()
    {
        return $this->ratings()
            ->where('rater_id', $this->rider_id)
            ->where('rated_user_id', $this->driver_id)
            ->where('type', 'driver')
            ->first();
    }

    /**
     * Get rating given by driver to rider
     */
    public function getDriverToRiderRating()
    {
        return $this->ratings()
            ->where('rater_id', $this->driver_id)
            ->where('rated_user_id', $this->rider_id)
            ->where('type', 'rider')
            ->first();
    }
}