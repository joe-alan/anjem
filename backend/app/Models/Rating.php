<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rating model for Anjem ride-sharing platform
 *
 * Handles ratings between riders and drivers with JSONB tags for detailed feedback
 */
class Rating extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     * The ratings table only has created_at, not updated_at
     */
    const UPDATED_AT = null;  // ✅ Disable updated_at timestamp

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ride_id',
        'rater_id',
        'rated_id',        // ✅ Match database column name
        'rating_type',     // ✅ Match database column name
        'score',           // ✅ Match database column name
        'feedback',        // ✅ Match database column name
        'tags',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'score' => 'integer',    // ✅ Match database column name
        'tags' => 'json',
        'created_at' => 'datetime',
    ];

    /**
     * Get the ride this rating is for.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * Get the user who gave the rating.
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    /**
     * Get the user who received the rating.
     */
    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_id');  // ✅ Match database column name
    }

    /**
     * Scope to get driver ratings only
     */
    public function scopeDriverRatings($query)
    {
        return $query->where('rating_type', 'rider_to_driver');  // ✅ Match database constraint
    }

    /**
     * Scope to get rider ratings only
     */
    public function scopeRiderRatings($query)
    {
        return $query->where('rating_type', 'driver_to_rider');  // ✅ Match database constraint
    }

    /**
     * Scope to get ratings for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('rated_id', $userId);  // ✅ Match database column name
    }

    /**
     * Scope to get ratings by a specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('rater_id', $userId);
    }

    /**
     * Scope to get high ratings (4-5 stars)
     */
    public function scopeHighRatings($query)
    {
        return $query->whereIn('score', [4, 5]);  // ✅ Match database column name
    }

    /**
     * Scope to get low ratings (1-2 stars)
     */
    public function scopeLowRatings($query)
    {
        return $query->whereIn('score', [1, 2]);  // ✅ Match database column name
    }

    /**
     * Check if this is a positive rating
     */
    public function isPositive(): bool
    {
        return $this->score >= 4;  // ✅ Match database column name
    }

    /**
     * Check if this is a negative rating
     */
    public function isNegative(): bool
    {
        return $this->score <= 2;  // ✅ Match database column name
    }

    /**
     * Get the rating as stars (for display)
     */
    public function getStarsAttribute(): string
    {
        return str_repeat('★', $this->score).str_repeat('☆', 5 - $this->score);  // ✅ Match database column name
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Update cached rating when a new rating is created
        static::created(function (Rating $rating) {
            $rating->updateCachedRating();
        });

        // Update cached rating when a rating is updated
        static::updated(function (Rating $rating) {
            $rating->updateCachedRating();
        });

        // Update cached rating when a rating is deleted
        static::deleted(function (Rating $rating) {
            $rating->updateCachedRating();
        });
    }

    /**
     * Update the cached rating average in the driver profile
     */
    protected function updateCachedRating(): void
    {
        $ratedUser = $this->ratedUser;

        // ✅ Check if this is a rating for a driver (rider_to_driver)
        if ($this->rating_type === 'rider_to_driver' && $ratedUser->driverProfile) {
            $ratings = static::forUser($this->rated_id)  // ✅ Match database column name
                ->where('rating_type', 'rider_to_driver')  // ✅ Match database column name
                ->get();

            if ($ratings->count() > 0) {
                $average = $ratings->avg('score');  // ✅ Match database column name
                $ratedUser->driverProfile->update([
                    'rating_average' => round($average, 2),
                    'rating_count' => $ratings->count(),
                ]);
            }
        }
    }

    /**
     * Predefined positive tags for ratings
     */
    public static function getPositiveTags(): array
    {
        return [
            'driver' => [
                'safe_driving',
                'friendly',
                'punctual',
                'clean_vehicle',
                'smooth_ride',
                'professional',
                'helpful',
                'good_communication',
            ],
            'rider' => [
                'punctual',
                'friendly',
                'respectful',
                'easy_to_find',
                'good_communication',
                'clean',
                'polite',
            ],
        ];
    }

    /**
     * Predefined negative tags for ratings
     */
    public static function getNegativeTags(): array
    {
        return [
            'driver' => [
                'late',
                'rude',
                'unsafe_driving',
                'dirty_vehicle',
                'wrong_route',
                'unprofessional',
                'no_communication',
                'vehicle_issues',
            ],
            'rider' => [
                'late',
                'rude',
                'hard_to_find',
                'messy',
                'no_communication',
                'disrespectful',
                'wrong_location',
            ],
        ];
    }

    /**
     * Get all available tags for a rating type
     */
    public static function getAvailableTags(string $type): array
    {
        return array_merge(
            static::getPositiveTags()[$type] ?? [],
            static::getNegativeTags()[$type] ?? []
        );
    }

    /**
     * Validate that tags are from the allowed list
     */
    public function validateTags(): bool
    {
        if (! $this->tags) {
            return true; // No tags is valid
        }

        // ✅ Determine type from rating_type (rider_to_driver = driver rating, driver_to_rider = rider rating)
        $type = $this->rating_type === 'rider_to_driver' ? 'driver' : 'rider';
        $allowedTags = static::getAvailableTags($type);
        $providedTags = is_array($this->tags) ? $this->tags : [];

        foreach ($providedTags as $tag) {
            if (! in_array($tag, $allowedTags)) {
                return false;
            }
        }

        return true;
    }
}
