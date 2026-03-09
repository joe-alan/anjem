<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_request_id' => $this->ride_request_id,
            'status' => $this->status,
            'passenger_count' => $this->passenger_count,
            'estimated_fare_rp' => $this->estimated_fare_rp,
            'actual_fare_rp' => $this->actual_fare_rp,
            'final_fare_rp' => $this->finalFare,
            'actual_distance_km' => $this->actual_distance_km,
            'actual_duration_minutes' => $this->actual_duration_minutes,
            'special_requests' => $this->special_requests,
            'driver_notes' => $this->driver_notes,
            'route_geometry' => $this->when(
                $this->relationLoaded('rideRequest'),
                fn() => $this->rideRequest?->route_geometry
            ),

            // Timestamps
            'driver_accepted_at' => $this->driver_accepted_at?->toISOString(),
            'pickup_time' => $this->pickup_time?->toISOString(),
            'dropoff_time' => $this->dropoff_time?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Relationships
            'rider' => new UserResource($this->whenLoaded('rider')),
            'driver' => new UserResource($this->whenLoaded('driver')),
            'pickup_location' => new LocationResource($this->whenLoaded('pickupLocation')),
            'destination_location' => new LocationResource($this->whenLoaded('destinationLocation')),
            'ride_request' => new RideRequestResource($this->whenLoaded('rideRequest')),

            // Ratings
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
            'can_rate' => $this->when(
                $this->status === 'completed',
                function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return false;
                    }

                    // Check if user already rated this ride
                    return ! $this->ratings()
                        ->where('rater_id', $user->id)
                        ->exists();
                }
            ),
        ];
    }
}
