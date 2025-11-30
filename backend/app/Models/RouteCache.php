<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RouteCache model for Anjem ride-sharing platform
 *
 * Caches Mapbox Directions API responses for frequently requested routes
 * to reduce API costs and improve response times. Routes are cached with a 7-day TTL.
 */
class RouteCache extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'origin_location_id',
        'destination_location_id',
        'route_geometry',
        'distance_meters',
        'duration_minutes',
        'profile',
        'last_fetched_at',
        'fetch_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'route_geometry' => 'json',
        'distance_meters' => 'integer',
        'duration_minutes' => 'integer',
        'fetch_count' => 'integer',
        'last_fetched_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'route_cache';

    /**
     * Get the origin location
     */
    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    /**
     * Get the destination location
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    /**
     * Scope to get routes that are fresh (within TTL)
     *
     * @param  int  $ttlDays  Time to live in days (default: 7)
     */
    public function scopeFresh($query, int $ttlDays = 7)
    {
        $cutoffDate = Carbon::now()->subDays($ttlDays);

        return $query->where('last_fetched_at', '>=', $cutoffDate);
    }

    /**
     * Scope to get routes that are stale (beyond TTL)
     *
     * @param  int  $ttlDays  Time to live in days (default: 7)
     */
    public function scopeStale($query, int $ttlDays = 7)
    {
        $cutoffDate = Carbon::now()->subDays($ttlDays);

        return $query->where('last_fetched_at', '<', $cutoffDate);
    }

    /**
     * Scope to find cached route by origin and destination
     */
    public function scopeForRoute($query, int $originId, int $destinationId, string $profile = 'driving')
    {
        return $query->where([
            'origin_location_id' => $originId,
            'destination_location_id' => $destinationId,
            'profile' => $profile,
        ]);
    }

    /**
     * Check if this cached route is fresh (within TTL)
     */
    public function isFresh(int $ttlDays = 7): bool
    {
        $cutoffDate = Carbon::now()->subDays($ttlDays);

        return $this->last_fetched_at >= $cutoffDate;
    }

    /**
     * Check if this cached route is stale (beyond TTL)
     */
    public function isStale(int $ttlDays = 7): bool
    {
        return ! $this->isFresh($ttlDays);
    }

    /**
     * Increment fetch count when route is reused
     */
    public function incrementFetchCount(): void
    {
        $this->increment('fetch_count');
    }

    /**
     * Refresh the route with new data from Mapbox API
     */
    public function refreshRouteData(array $routeData): void
    {
        $this->update([
            'route_geometry' => $routeData['geometry'],
            'distance_meters' => $routeData['distance_meters'],
            'duration_minutes' => $routeData['duration_minutes'],
            'last_fetched_at' => Carbon::now(),
        ]);

        $this->incrementFetchCount();
    }

    /**
     * Get most frequently used routes
     */
    public static function getMostPopularRoutes(int $limit = 10)
    {
        return static::orderBy('fetch_count', 'desc')
            ->with(['originLocation', 'destinationLocation'])
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up stale routes (older than TTL)
     */
    public static function cleanupStaleRoutes(int $ttlDays = 30): int
    {
        return static::stale($ttlDays)->delete();
    }

    /**
     * Get cache statistics
     */
    public static function getCacheStats(): array
    {
        $totalRoutes = static::query()->count();
        $freshRoutes = static::query()->fresh(7)->count();
        $staleRoutes = static::query()->stale(7)->count();
        $totalFetches = static::query()->sum('fetch_count');
        $avgFetchesPerRoute = $totalRoutes > 0 ? $totalFetches / $totalRoutes : 0;

        return [
            'total_cached_routes' => $totalRoutes,
            'fresh_routes' => $freshRoutes,
            'stale_routes' => $staleRoutes,
            'total_cache_hits' => $totalFetches,
            'avg_reuse_per_route' => round($avgFetchesPerRoute, 2),
            'cache_hit_rate' => $totalFetches > 0 ? round(($totalFetches - $totalRoutes) / $totalFetches * 100, 2) : 0,
        ];
    }
}
