<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
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
            'is_beacon' => $this->is_beacon,
            'is_active' => $this->is_active,
            'beacon_capacity' => $this->when($this->is_beacon, $this->beacon_capacity),
            'current_queue_size' => $this->when($this->is_beacon, $this->current_queue_size),
            'has_capacity' => $this->when($this->is_beacon, function() {
                return $this->hasCapacity();
            }),
            'coordinates' => [
                'latitude' => $this->coordinates->latitude,
                'longitude' => $this->coordinates->longitude,
            ],
            'address' => $this->address,
            'description' => $this->description,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
