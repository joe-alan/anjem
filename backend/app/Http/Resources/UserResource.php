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
            'firebase_uid' => $this->firebase_uid, // Mobile expects this field
            'user_type' => $this->role, // API backward compatibility: fetch from role
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_active_at' => $this->last_active_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Include driver profile if user is a driver
            'driver_profile' => $this->when(
                in_array($this->role, ['driver', 'both', 'admin']),
                function () {
                    return $this->driverProfile ? [
                        'id' => $this->driverProfile->id,
                        'user_id' => $this->driverProfile->user_id,
                        'license_number' => $this->driverProfile->driver_license_number ?? '',
                        'vehicle_info' => [ // Mobile expects consolidated vehicle_info Map
                            'type' => $this->driverProfile->vehicle_type,
                            'make' => $this->driverProfile->vehicle_make,
                            'model' => $this->driverProfile->vehicle_model,
                            'year' => $this->driverProfile->vehicle_year,
                            'color' => $this->driverProfile->vehicle_color,
                            'plate' => $this->driverProfile->vehicle_plate,
                        ],
                        // Keep individual fields for backward compatibility
                        'vehicle_type' => $this->driverProfile->vehicle_type,
                        'vehicle_model' => $this->driverProfile->vehicle_model,
                        'vehicle_year' => $this->driverProfile->vehicle_year,
                        'plate_number' => $this->driverProfile->vehicle_plate,
                        'is_verified' => $this->driverProfile->is_verified,
                        'is_available' => $this->driverProfile->went_online_at !== null,
                        'status' => $this->driverProfile->went_online_at !== null ? 'online' : 'offline',
                        'rating' => (float) ($this->driverProfile->rating_average ?? 5.0), // ✅ Use rating_average from migration
                        'rating_count' => $this->driverProfile->rating_count ?? 0, // ✅ Include rating count
                        'total_rides'     => $this->driverProfile->total_rides_given ?? 0,
                        'credits_balance' => $this->driverProfile->credits_balance ?? 0,
                        'created_at' => $this->driverProfile->created_at->toISOString(),
                        'updated_at' => $this->driverProfile->updated_at->toISOString(),
                    ] : null;
                }
            ),
        ];
    }
}
