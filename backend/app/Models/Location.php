<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * Location model for Anjem ride-sharing platform
 *
 * Stores both beacon locations (pickup points) and cached P2P destinations
 * with PostGIS spatial support for efficient proximity queries.
 */
class Location extends Model
{
    use HasFactory, HasSpatial;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'coordinates',
        'radius_m',
        'is_beacon',
        'usage_count',
        'google_place_id',
        'is_active',
        'beacon_capacity',
        'current_queue_size',
        'description',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'coordinates' => Point::class,
        'is_active' => 'boolean',
        'is_beacon' => 'boolean',
        'radius_m' => 'integer',
        'usage_count' => 'integer',
        'beacon_capacity' => 'integer',
        'current_queue_size' => 'integer',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get ride requests that originate from this location.
     */
    public function originRideRequests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'pickup_location_id');
    }

    /**
     * Get ride requests that end at this location.
     */
    public function destinationRideRequests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'destination_location_id');
    }

    /**
     * Get driver queue entries for this beacon location.
     */
    public function driverQueue(): HasMany
    {
        return $this->hasMany(DriverQueue::class, 'beacon_id');
    }

    /**
     * Get rides that picked up from this location.
     */
    public function pickupRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'pickup_location_id');
    }

    /**
     * Get rides that dropped off at this location.
     */
    public function dropoffRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'destination_location_id');
    }

    /**
     * Scope to get only beacon locations
     */
    public function scopeBeacons($query)
    {
        return $query->where('is_beacon', true);
    }

    /**
     * Scope to get only P2P destination locations
     */
    public function scopeDestinations($query)
    {
        return $query->where('is_beacon', false);
    }

    /**
     * Scope to get only active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to find locations within a certain distance of a point
     */
    public function scopeWithinDistance($query, Point $point, float $distanceInMeters = 1000)
    {
        return $query->whereRaw(
            'ST_DWithin(coordinates, ?, ?)',
            [$point, $distanceInMeters]
        );
    }

    /**
     * Scope to find the nearest locations to a point
     */
    public function scopeOrderByDistance($query, Point $point)
    {
        return $query->orderByRaw('ST_Distance(coordinates, ?) ASC', [$point]);
    }

    /**
     * Check if this is a beacon location
     */
    public function isBeacon(): bool
    {
        return $this->is_beacon === true;
    }

    /**
     * Check if this is a P2P destination
     */
    public function isDestination(): bool
    {
        return $this->is_beacon === false;
    }

    /**
     * Check if beacon has capacity for more drivers
     */
    public function hasCapacity(): bool
    {
        if (! $this->isBeacon()) {
            return false;
        }

        return ($this->current_queue_size ?? 0) < ($this->beacon_capacity ?? 10);
    }

    /**
     * Get the distance to a specific point in meters using PostGIS
     * Uses ::geography cast so ST_Distance returns metres, not degrees
     */
    public function getDistanceTo(Point $point): float
    {
        $result = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT ST_Distance(coordinates::geography, ST_MakePoint(?, ?)::geography) as distance FROM locations WHERE id = ?',
            [$point->longitude, $point->latitude, $this->id]
        );

        return $result->distance ?? 0;
    }

    /**
     * Update current queue size for beacon locations
     */
    public function updateQueueSize(): void
    {
        if ($this->isBeacon()) {
            $queueSize = $this->driverQueue()
                ->where('status', 'waiting')
                ->count();

            $this->update(['current_queue_size' => $queueSize]);
        }
    }

    /**
     * Get coordinates as latitude and longitude array
     */
    public function getLatLngAttribute(): array
    {
        if (! $this->coordinates) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => $this->coordinates->latitude,
            'lng' => $this->coordinates->longitude,
        ];
    }

    /**
     * Find nearby beacons within walking distance
     */
    public static function findNearbyBeacons(float $latitude, float $longitude, float $radiusMeters = 500): \Illuminate\Database\Eloquent\Collection
    {
        $point = new Point($latitude, $longitude, 4326);

        return static::beacons()
            ->active()
            ->withinDistance($point, $radiusMeters)
            ->orderByDistance($point)
            ->get();
    }

    /**
     * Find or create a P2P destination location
     */
    public static function findOrCreateDestination(string $name, string $address, float $latitude, float $longitude): self
    {
        $point = new Point($latitude, $longitude, 4326);

        // Check if a destination already exists within 50 meters
        $existing = static::destinations()
            ->withinDistance($point, 50)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create new destination
        return static::create([
            'name' => $name,
            'address' => $address,
            'coordinates' => $point,
            'is_beacon' => false, // P2P destination
            'is_active' => true,
        ]);
    }
}
