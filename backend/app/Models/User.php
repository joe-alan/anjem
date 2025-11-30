<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
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
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'role', // admin, rider, driver, both
        'phone_number',
        'phone_verified_at',
        'fcm_token',
        'profile_picture',
        'emergency_contact',
        'preferred_payment',
        'is_active',
        'last_active_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
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
        'last_active_at' => 'datetime',
        'rider_rating_avg' => 'decimal:2',
        'total_rides_taken' => 'integer',
        'password' => 'hashed',
        'role' => 'string', // admin, rider, driver, both
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
        return $this->hasMany(Rating::class, 'rated_id');
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
     * Check if user can be a driver
     */
    public function canBeDriver(): bool
    {
        return in_array($this->role, ['driver', 'both', 'admin']);
    }

    /**
     * Check if user is currently online as a driver
     */
    public function isDriverOnline(): bool
    {
        return $this->isDriver() && $this->driverProfile->went_online_at !== null;
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user can access admin panel
     */
    public function canAccessAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user can act as rider
     */
    public function canBeRider(): bool
    {
        return in_array($this->role, ['rider', 'both', 'admin']);
    }

    /**
     * Check if user can act as driver (updated to use role)
     */
    public function canActAsDriver(): bool
    {
        return in_array($this->role, ['driver', 'both']) && $this->driverProfile()->exists();
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
            ->where('rating_type', 'driver')
            ->avg('score');
    }

    /**
     * Get average rating as a rider
     */
    public function getRiderRatingAttribute(): ?float
    {
        return $this->ratingsReceived()
            ->where('rating_type', 'rider')
            ->avg('score');
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
    public function createTokenWithAbilities(bool $asDriver = false, bool $asAdmin = false): string
    {
        $abilities = [
            'profile:read',
            'profile:update',
            'locations:read',
            'notifications:read',
        ];

        // Admin gets all abilities
        if ($asAdmin && $this->isAdmin()) {
            $abilities = array_merge($abilities, [
                // Admin-specific abilities
                'admin:view-users',
                'admin:manage-users',
                'admin:view-rides',
                'admin:manage-rides',
                'admin:view-analytics',
                'admin:view-monitoring',
                'admin:manage-drivers',
                'admin:manage-riders',
                'admin:suspend-users',
                // Plus all rider and driver abilities
                'rider:request-ride',
                'rider:cancel-ride',
                'rider:rate-driver',
                'rider:view-history',
                'driver:go-online',
                'driver:accept-ride',
                'driver:complete-ride',
                'driver:view-queue',
                'driver:update-location',
            ]);
            $tokenName = 'admin-token';
        } elseif ($asDriver && $this->canBeDriver()) {
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

    /**
     * Create or find user from Firebase authentication
     */
    public static function findOrCreateFromFirebase(array $firebaseData): self
    {
        $user = static::where('firebase_uid', $firebaseData['uid'])->first();

        if (! $user) {
            $user = static::where('email', $firebaseData['email'])->first();
        }

        if (! $user) {
            $user = static::create([
                'name' => $firebaseData['name'] ?? 'User',
                'email' => $firebaseData['email'],
                'firebase_uid' => $firebaseData['uid'],
                'email_verified_at' => $firebaseData['email_verified'] ? now() : null,
                'phone_number' => $firebaseData['phone'] ?? null,
                'phone_verified_at' => $firebaseData['phone_verified'] ? now() : null,
                'profile_picture' => $firebaseData['picture'] ?? null,
                'is_active' => true,
                'last_active_at' => now(),
            ]);
        } else {
            // Update existing user with Firebase data
            $user->update([
                'firebase_uid' => $firebaseData['uid'],
                'last_active_at' => now(),
            ]);
        }

        return $user;
    }

    /**
     * Update FCM token for push notifications
     */
    public function updateFcmToken(?string $token): void
    {
        $this->update(['fcm_token' => $token]);
    }
}
