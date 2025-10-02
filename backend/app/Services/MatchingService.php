<?php

namespace App\Services;

use App\Models\RideRequest;
use App\Models\User;
use Carbon\Carbon;

class MatchingService
{
    /**
     * Find best driver for a ride request based on matching algorithm
     */
    public function findBestDriver(RideRequest $request): ?User
    {
        $availableDrivers = User::where('user_type', 'driver')
            ->where('is_active', true)
            ->whereHas('driverProfile')
            ->whereDoesntHave('driverRides', function ($query) {
                $query->whereIn('status', ['matched', 'accepted', 'in_progress']);
            })
            ->get();

        if ($availableDrivers->isEmpty()) {
            return null;
        }

        // Calculate scores for each driver
        $scoredDrivers = $availableDrivers->map(function (User $driver) {
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
    private function calculateDriverScore(User $driver): float
    {
        $driverProfile = $driver->driverProfile;

        $reliabilityScore = $driverProfile->reliability_score ?? 0;
        $onTimeRate = ($driverProfile->on_time_rate ?? 0) * 100; // Convert to points
        $experiencePoints = min($driverProfile->experience_points ?? 0, 1000); // Cap at 1000

        // Calculate queue age in hours since went online
        $queueAge = 0;
        if ($driverProfile->went_online_at) {
            $queueAge = Carbon::parse($driverProfile->went_online_at)
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

        if (! $bestDriver) {
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
