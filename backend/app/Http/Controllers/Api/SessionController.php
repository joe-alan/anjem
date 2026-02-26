<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RideRequestResource;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(private RideService $rideService)
    {
    }

    /**
     * Return the current resumable state for the authenticated user.
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('driverProfile');

        $activeRide = $this->rideService->getActiveRide($user->id);
        $activeRequest = $this->rideService->getActiveRideRequest($user->id);

        return response()->json([
            'success' => true,
            'data' => [
                'state' => $this->determineState($activeRide, $activeRequest),
                'ride_role' => $this->determineRideRole($activeRide, $user->id),
                'active_ride' => $activeRide ? new RideResource($activeRide) : null,
                'active_request' => $activeRequest ? new RideRequestResource($activeRequest) : null,
                'driver_context' => $this->buildDriverContext($user, $activeRide),
            ],
        ]);
    }

    private function determineState(?Ride $ride, ?RideRequest $request): string
    {
        if ($ride) {
            return 'ride_active';
        }

        if ($request) {
            return match ($request->status) {
                'matched' => 'request_matched',
                'in_progress' => 'request_in_progress',
                default => 'request_pending',
            };
        }

        return 'idle';
    }

    private function determineRideRole(?Ride $ride, int $userId): ?string
    {
        if (! $ride) {
            return null;
        }

        if ($ride->rider_id === $userId) {
            return 'rider';
        }

        if ($ride->driver_id === $userId) {
            return 'driver';
        }

        return null;
    }

    private function buildDriverContext(User $user, ?Ride $activeRide): array
    {
        $driverProfile = $user->driverProfile;

        return [
            'is_driver' => in_array($user->role, ['driver', 'both', 'admin'], true),
            'is_online' => $driverProfile?->went_online_at !== null,
            'went_online_at' => $driverProfile?->went_online_at?->toISOString(),
            'active_ride_id' => ($activeRide && $activeRide->driver_id === $user->id)
                ? $activeRide->id
                : null,
        ];
    }
}
