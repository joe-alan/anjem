<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideRequestResource extends JsonResource
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
            'status' => $this->status,
            'passenger_count' => $this->passenger_count,
            'estimated_distance_km' => $this->estimated_distance_km,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'estimated_fare_rp' => $this->estimated_fare_rp,
            'route_geometry' => $this->route_geometry,
            'special_requests' => $this->special_requests,
            'expires_at' => $this->expires_at?->toISOString(),
            'matched_at' => $this->matched_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Relationships
            'rider' => new UserResource($this->whenLoaded('rider')),
            'pickup_location' => new LocationResource($this->whenLoaded('pickupLocation')),
            'destination_location' => new LocationResource($this->whenLoaded('destinationLocation')),

            // Status checks
            'is_expired' => $this->expires_at && $this->expires_at->isPast(),
            'is_active' => in_array($this->status, ['pending', 'matched']),
            'can_cancel' => in_array($this->status, ['pending', 'matched']),
        ];
    }
}
