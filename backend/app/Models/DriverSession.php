<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DriverSession model for Anjem ride-sharing platform
 *
 * Tracks driver sessions for analytics and performance monitoring
 */
class DriverSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'started_at',
        'ended_at',
        'total_online_minutes',
        'rides_completed',
        'total_earnings_rp',
        'total_distance_km',
        'locations_visited',
        'peak_hours_minutes',
        'off_peak_hours_minutes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_online_minutes' => 'integer',
        'rides_completed' => 'integer',
        'total_earnings_rp' => 'integer',
        'total_distance_km' => 'decimal:2',
        'locations_visited' => 'json',
        'peak_hours_minutes' => 'integer',
        'off_peak_hours_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the driver for this session.
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
     * Scope to get active sessions (not ended)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope to get completed sessions
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('ended_at');
    }

    /**
     * Scope to get sessions for a specific driver
     */
    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope to get sessions within date range
     */
    public function scopeInDateRange($query, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get sessions from today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    /**
     * Scope to get sessions from this week
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('started_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Check if session is currently active
     */
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    /**
     * Start a new driver session
     */
    public static function startSession(int $driverId): self
    {
        // End any existing active session
        $existingSession = static::forDriver($driverId)->active()->first();
        if ($existingSession) {
            $existingSession->endSession();
        }

        return static::create([
            'driver_id' => $driverId,
            'started_at' => now(),
            'locations_visited' => [],
            'rides_completed' => 0,
            'total_earnings_rp' => 0,
            'total_distance_km' => 0,
            'peak_hours_minutes' => 0,
            'off_peak_hours_minutes' => 0,
        ]);
    }

    /**
     * End the current session
     */
    public function endSession(): void
    {
        if ($this->isActive()) {
            $totalMinutes = now()->diffInMinutes($this->started_at);

            $this->update([
                'ended_at' => now(),
                'total_online_minutes' => $totalMinutes,
            ]);

            $this->calculatePeakOffPeakHours();
        }
    }

    /**
     * Update session when a ride is completed
     */
    public function updateWithRide(Ride $ride): void
    {
        if (!$this->isActive()) {
            return;
        }

        $updates = [
            'rides_completed' => $this->rides_completed + 1,
            'total_earnings_rp' => $this->total_earnings_rp + $ride->final_fare,
        ];

        if ($ride->actual_distance_km) {
            $updates['total_distance_km'] = $this->total_distance_km + $ride->actual_distance_km;
        }

        // Track visited locations
        $visitedLocations = $this->locations_visited ?? [];
        if ($ride->pickup_location_id && !in_array($ride->pickup_location_id, $visitedLocations)) {
            $visitedLocations[] = $ride->pickup_location_id;
        }
        if ($ride->destination_location_id && !in_array($ride->destination_location_id, $visitedLocations)) {
            $visitedLocations[] = $ride->destination_location_id;
        }
        $updates['locations_visited'] = $visitedLocations;

        $this->update($updates);
    }

    /**
     * Calculate peak and off-peak hours for the session
     */
    protected function calculatePeakOffPeakHours(): void
    {
        if (!$this->ended_at) {
            return;
        }

        $peakMinutes = 0;
        $offPeakMinutes = 0;

        $current = $this->started_at->copy();
        while ($current < $this->ended_at) {
            $nextHour = $current->copy()->addHour()->startOfHour();
            $endTime = $nextHour < $this->ended_at ? $nextHour : $this->ended_at;

            $hourMinutes = $current->diffInMinutes($endTime);

            if ($this->isPeakHour($current)) {
                $peakMinutes += $hourMinutes;
            } else {
                $offPeakMinutes += $hourMinutes;
            }

            $current = $nextHour;
        }

        $this->update([
            'peak_hours_minutes' => $peakMinutes,
            'off_peak_hours_minutes' => $offPeakMinutes,
        ]);
    }

    /**
     * Check if a given time is during peak hours
     */
    protected function isPeakHour(\Carbon\Carbon $time): bool
    {
        $hour = $time->hour;
        $dayOfWeek = $time->dayOfWeek;

        // Weekend peak hours (Friday 18:00 - Sunday 23:59)
        if ($dayOfWeek >= 5) {
            return $hour >= 18 || $hour <= 23;
        }

        // Weekday peak hours (7:00-9:00, 17:00-19:00)
        return ($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19);
    }

    /**
     * Get session earnings per hour
     */
    public function getEarningsPerHourAttribute(): float
    {
        if (!$this->total_online_minutes || $this->total_online_minutes === 0) {
            return 0;
        }

        $hours = $this->total_online_minutes / 60;
        return round($this->total_earnings_rp / $hours, 2);
    }

    /**
     * Get session rides per hour
     */
    public function getRidesPerHourAttribute(): float
    {
        if (!$this->total_online_minutes || $this->total_online_minutes === 0) {
            return 0;
        }

        $hours = $this->total_online_minutes / 60;
        return round($this->rides_completed / $hours, 2);
    }

    /**
     * Get session summary for analytics
     */
    public function getSummary(): array
    {
        return [
            'session_id' => $this->id,
            'driver_id' => $this->driver_id,
            'date' => $this->started_at->toDateString(),
            'duration_hours' => round($this->total_online_minutes / 60, 2),
            'rides_completed' => $this->rides_completed,
            'total_earnings_rp' => $this->total_earnings_rp,
            'earnings_per_hour' => $this->earnings_per_hour,
            'rides_per_hour' => $this->rides_per_hour,
            'total_distance_km' => $this->total_distance_km,
            'locations_count' => count($this->locations_visited ?? []),
            'peak_hours_percent' => $this->total_online_minutes > 0
                ? round(($this->peak_hours_minutes / $this->total_online_minutes) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get driver performance metrics for a date range
     */
    public static function getDriverMetrics(int $driverId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $sessions = static::forDriver($driverId)
            ->completed()
            ->inDateRange($startDate, $endDate)
            ->get();

        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'total_hours' => 0,
                'total_rides' => 0,
                'total_earnings' => 0,
                'average_earnings_per_hour' => 0,
                'average_rides_per_hour' => 0,
            ];
        }

        return [
            'total_sessions' => $sessions->count(),
            'total_hours' => round($sessions->sum('total_online_minutes') / 60, 2),
            'total_rides' => $sessions->sum('rides_completed'),
            'total_earnings' => $sessions->sum('total_earnings_rp'),
            'average_earnings_per_hour' => round($sessions->avg('earnings_per_hour'), 2),
            'average_rides_per_hour' => round($sessions->avg('rides_per_hour'), 2),
            'total_distance_km' => round($sessions->sum('total_distance_km'), 2),
        ];
    }
}