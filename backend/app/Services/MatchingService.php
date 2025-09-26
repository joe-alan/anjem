<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Request as RideRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MatchingService
{
    /**
     * Find best driver for a ride request based on matching algorithm
     */
    public function findBestDriver(RideRequest $request): ?Driver
    {
        $availableDrivers = Driver::where('is_online', true)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->whereDoesntHave('rides', function ($query) {
                $query->whereIn('status', ['assigned', 'en_route', 'started']);
            })
            ->get();

        if ($availableDrivers->isEmpty()) {
            return null;
        }

        // Calculate scores for each driver
        $scoredDrivers = $availableDrivers->map(function (Driver $driver) {
            return [
                'driver' => $driver,
                'score' => $this->calculateDriverScore($driver),
            ];
        });

        // Sort by score descending and return the best driver
        $bestMatch = $scoredDrivers->sortByDesc('score')->first();

        return $bestMatch['score'] > 0 ? $bestMatch['driver'] : null;
    }

    /**
     * Calculate driver score based on the algorithm:
     * score = reliability_score + on_time_rate + experience_points - 0.5*queue_age
     */
    private function calculateDriverScore(Driver $driver): float
    {
        $reliabilityScore = $driver->reliability_score ?? 0;
        $onTimeRate = ($driver->on_time_rate ?? 0) * 100; // Convert to points
        $experiencePoints = min($driver->experience_points ?? 0, 1000); // Cap at 1000

        // Calculate queue age in hours since went online
        $queueAge = 0;
        if ($driver->went_online_at) {
            $queueAge = Carbon::parse($driver->went_online_at)
                ->diffInHours(Carbon::now());
        }

        $score = $reliabilityScore + $onTimeRate + ($experiencePoints / 10) - (0.5 * $queueAge);

        // Minimum score threshold to prevent negative values
        return max($score, 0);
    }

    /**
     * Match a request with the best available driver
     */
    public function matchRequest(RideRequest $request): bool
    {
        $bestDriver = $this->findBestDriver($request);

        if (!$bestDriver) {
            return false;
        }

        // Update request with matched driver
        $request->update([
            'matched_driver_id' => $bestDriver->id,
            'status' => 'matched',
            'matched_at' => Carbon::now(),
        ]);

        // TODO: Send notification to driver
        // TODO: Broadcast matching event via WebSocket

        return true;
    }
}