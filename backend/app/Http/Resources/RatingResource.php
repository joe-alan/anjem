<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            'tags' => $this->tags,
            'created_at' => $this->created_at->toISOString(),

            // Relationships
            'rater' => new UserResource($this->whenLoaded('rater')),
            'rated_user' => new UserResource($this->whenLoaded('ratedUser')),
            'ride' => $this->when($this->relationLoaded('ride'), function () {
                return ['id' => $this->ride->id, 'status' => $this->ride->status];
            }),
        ];
    }
}
