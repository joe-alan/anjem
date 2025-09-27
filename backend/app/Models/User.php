<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Unified User model for Anjem ride-sharing platform
 *
 * A user can be both a rider and a driver. Driver-specific information
 * is stored in the optional DriverProfile relationship.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'firebase_uid',
        'name',
        'profile_picture',
        'phone',
        'phone_verified_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'firebase_uid',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the driver profile for this user (if they are a driver)
     */
    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * Get all ride requests made by this user as a rider
     */
    public function rideRequests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'rider_id');
    }

    /**
     * Get all rides completed by this user as a driver
     */
    public function driverRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    /**
     * Get all rides taken by this user as a rider
     */
    public function riderRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'rider_id');
    }

    /**
     * Get all ratings given by this user
     */
    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    /**
     * Get all ratings received by this user
     */
    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    /**
     * Get driver sessions for analytics
     */
    public function driverSessions(): HasMany
    {
        return $this->hasMany(DriverSession::class, 'driver_id');
    }

    /**
     * Check if user is a driver (has a driver profile)
     */
    public function isDriver(): bool
    {
        return $this->driverProfile()->exists();
    }

    /**
     * Check if user is currently online as a driver
     */
    public function isDriverOnline(): bool
    {
        return $this->isDriver() && $this->driverProfile->status === 'online';
    }

    /**
     * Get average rating as a driver
     */
    public function getDriverRatingAttribute(): ?float
    {
        if (! $this->isDriver()) {
            return null;
        }

        return $this->ratingsReceived()
            ->where('type', 'driver')
            ->avg('rating');
    }

    /**
     * Get average rating as a rider
     */
    public function getRiderRatingAttribute(): ?float
    {
        return $this->ratingsReceived()
            ->where('type', 'rider')
            ->avg('rating');
    }

    /**
     * Scope to get active users only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Create Sanctum token with appropriate abilities based on user role
     */
    public function createTokenWithAbilities(bool $asDriver = false): string
    {
        $abilities = [
            'profile:read',
            'profile:update',
            'locations:read',
            'notifications:read',
        ];

        if ($asDriver && $this->isDriver()) {
            $abilities = array_merge($abilities, [
                'driver:go-online',
                'driver:accept-ride',
                'driver:complete-ride',
                'driver:view-queue',
                'driver:update-location',
            ]);
            $tokenName = 'driver-token';
        } else {
            $abilities = array_merge($abilities, [
                'rider:request-ride',
                'rider:cancel-ride',
                'rider:rate-driver',
                'rider:view-history',
            ]);
            $tokenName = 'rider-token';
        }

        return $this->createToken($tokenName, $abilities)->plainTextToken;
    }
}
