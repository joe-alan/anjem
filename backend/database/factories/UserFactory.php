<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'firebase_uid' => 'firebase_'.Str::random(20),
            'user_type' => fake()->randomElement(['rider', 'driver', 'both']),
            'phone_number' => fake()->phoneNumber(),
            'phone_verified_at' => fake()->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            'email_verified_at' => now(),
            'fcm_token' => fake()->optional(0.7)->regexify('[a-zA-Z0-9_-]{100,200}'),
            'profile_picture' => fake()->optional(0.3)->imageUrl(200, 200, 'people'),
            'emergency_contact' => fake()->phoneNumber(),
            'preferred_payment' => fake()->randomElement(['cash', 'digital']),
            'rider_rating_avg' => fake()->randomFloat(2, 3.5, 5.0),
            'total_rides_taken' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'last_active_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a rider-only user.
     */
    public function rider(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'rider',
        ]);
    }

    /**
     * Create a driver-only user.
     */
    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'driver',
        ]);
    }

    /**
     * Create a user who can be both rider and driver.
     */
    public function both(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'both',
        ]);
    }

    /**
     * Firebase authenticated user state.
     */
    public function firebaseAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null, // Firebase handles auth
            'email_verified_at' => now(),
            'fcm_token' => fake()->regexify('[a-zA-Z0-9_-]{150}'),
        ]);
    }
}
