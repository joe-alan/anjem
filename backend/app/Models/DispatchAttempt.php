<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchAttempt extends Model
{
    protected $fillable = [
        'ride_request_id',
        'driver_id',
        'dispatched_at',
        'responded_at',
        'response',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopeForRideRequest($query, int $rideRequestId)
    {
        return $query->where('ride_request_id', $rideRequestId);
    }

    public function scopePending($query)
    {
        return $query->where('response', 'pending');
    }

    public function getResponseTimeSeconds(): ?int
    {
        if (! $this->responded_at) {
            return null;
        }

        return $this->dispatched_at->diffInSeconds($this->responded_at);
    }
}
