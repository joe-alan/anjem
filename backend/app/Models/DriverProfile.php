<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * Driver Profile model for Anjem ride-sharing platform
 *
 * Contains driver-specific information and current location.
 * Only created when a user wants to become a driver.
 */
class DriverProfile extends Model
{
    use HasFactory, HasSpatial;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_make',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'license_plate',
        'driver_license_number',
        'current_location',
        'status',
        'is_verified',
        'rating_average',
        'rating_count',
        'total_rides',
        'last_location_update',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'driver_license_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_location' => Point::class,
        'is_verified' => 'boolean',
        'rating_average' => 'decimal:2',
        'rating_count' => 'integer',
        'total_rides' => 'integer',
        'last_location_update' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the driver profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the driver queue entries for this driver.
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(DriverQueue::class, 'driver_id');
    }

    /**
     * Get the current active queue entry for this driver.
     */
    public function currentQueueEntry()
    {
        return $this->queueEntries()
            ->where('status', 'waiting')
            ->first();
    }

    /**
     * Check if driver is currently online
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Check if driver is currently available for rides
     */
    public function isAvailable(): bool
    {
        return $this->status === 'online' && $this->is_verified;
    }

    /**
     * Scope to get only online drivers
     */
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    /**
     * Scope to get only verified drivers
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get available drivers (online and verified)
     */
    public function scopeAvailable($query)
    {
        return $query->online()->verified();
    }

    /**
     * Scope to find drivers within a certain distance of a point
     */
    public function scopeWithinDistance($query, Point $point, float $distanceInMeters = 5000)
    {
        return $query->whereRaw(
            'ST_DWithin(current_location, ?, ?)',
            [$point, $distanceInMeters]
        );
    }

    /**
     * Update driver location
     */
    public function updateLocation(float $latitude, float $longitude): void
    {
        $this->update([
            'current_location' => new Point($latitude, $longitude, 4326),
            'last_location_update' => now(),
        ]);
    }

    /**
     * Update driver rating after a new rating
     */
    public function updateRating(float $newRating): void
    {
        $newCount = $this->rating_count + 1;
        $newAverage = (($this->rating_average * $this->rating_count) + $newRating) / $newCount;

        $this->update([
            'rating_average' => round($newAverage, 2),
            'rating_count' => $newCount,
        ]);
    }

    /**
     * Increment total rides count
     */
    public function incrementRideCount(): void
    {
        $this->increment('total_rides');
    }

    /**
     * Get the distance to a specific point in meters
     */
    public function getDistanceTo(Point $point): ?float
    {
        if (! $this->current_location) {
            return null;
        }

        return $this->current_location->distanceTo($point);
    }
}
