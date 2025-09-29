<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_active_at' => $this->last_active_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Include driver profile if user is a driver
            'driver_profile' => $this->when(
                $this->user_type === 'driver' || $this->user_type === 'both',
                function () {
                    return $this->driverProfile ? [
                        'id' => $this->driverProfile->id,
                        'license_number' => $this->driverProfile->license_number,
                        'vehicle_type' => $this->driverProfile->vehicle_type,
                        'vehicle_model' => $this->driverProfile->vehicle_model,
                        'vehicle_year' => $this->driverProfile->vehicle_year,
                        'plate_number' => $this->driverProfile->plate_number,
                        'is_verified' => $this->driverProfile->is_verified,
                        'is_available' => $this->driverProfile->is_available,
                        'rating' => $this->driverProfile->rating,
                        'total_rides' => $this->driverProfile->total_rides,
                    ] : null;
                }
            ),
        ];
    }
}
