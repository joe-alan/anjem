<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverQueueResource extends JsonResource
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
            'position' => $this->position,
            'status' => $this->status,
            'joined_at' => $this->joined_at->toISOString(),
            'called_at' => $this->called_at?->toISOString(),
            'left_at' => $this->left_at?->toISOString(),
            'served_at' => $this->served_at?->toISOString(),
            'wait_time_minutes' => now()->diffInMinutes($this->joined_at),
            'estimated_wait_minutes' => $this->getEstimatedWaitTimeMinutes(),

            // Relationships
            'driver' => new UserResource($this->whenLoaded('driver')),
            'beacon' => new LocationResource($this->whenLoaded('beacon')),

            // Status checks
            'is_active' => $this->status === 'waiting',
            'is_called' => $this->status === 'called',
        ];
    }
}
