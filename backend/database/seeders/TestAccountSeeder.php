<?php

namespace Database\Seeders;

use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Rating;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\RouteCache;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use MatanYadaev\EloquentSpatial\Objects\Point;

class TestAccountSeeder extends Seeder
{
    public function run(): void
    {
        // History accounts (ride history, ratings, earnings)
        $rider = $this->seedUser('reviewer.rider@anjem.me', 'Test Rider', 'rider', '+6281234567890');
        $driver = $this->seedUser('reviewer.driver@anjem.me', 'Test Driver', 'driver', '+6281234567891');
        $driverProfile = $this->seedDriverProfile($driver, 'TEST000001', 'H 0000 TST', 'reviewer@students.undip.ac.id');
        $this->seedCompletedRidesAndRatings($rider, $driver, $driverProfile);
        $this->seedCreditTransaction($driver, $driverProfile, 50);

        // Active ride accounts (land on ride screens via SessionCheckWrapper)
        $activeRider = $this->seedUser('reviewer.rider.active@anjem.me', 'Test Rider Active', 'rider', '+6281234567892');
        $activeDriver = $this->seedUser('reviewer.driver.active@anjem.me', 'Test Driver Active', 'driver', '+6281234567893');
        $activeDriverProfile = $this->seedDriverProfile($activeDriver, 'TEST000002', 'H 0001 TST', 'reviewer2@students.undip.ac.id');
        $this->seedActiveRide($activeRider, $activeDriver, $activeDriverProfile);
        $this->seedCreditTransaction($activeDriver, $activeDriverProfile, 10);

        $this->command->info('Test accounts seeded for Play Store review.');
    }

    private function seedUser(string $email, string $name, string $role, string $phone): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => $role,
                'phone_number' => $phone,
                'password' => Hash::make('reviewer'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
    }

    private function seedDriverProfile(User $driver, string $studentId, string $plate, string $studentEmail): DriverProfile
    {
        return DriverProfile::firstOrCreate(
            ['user_id' => $driver->id],
            [
                'student_email' => $studentEmail,
                'student_id' => $studentId,
                'student_name' => $driver->name,
                'email_verified_at' => now(),
                'is_verified' => true,
                'vehicle_type' => 'motorcycle',
                'vehicle_color' => 'Hitam',
                'vehicle_plate' => $plate,
                'max_pickup_radius_km' => 5.0,
                'rating_average' => 0,
                'rating_count' => 0,
                'total_rides_given' => 0,
                'credits_balance' => 0,
                'credits_total_earned' => 0,
                'credits_total_spent' => 0,
            ]
        );
    }

    private function seedCompletedRidesAndRatings(User $rider, User $driver, DriverProfile $driverProfile): void
    {
        if ($rider->riderRides()->count() > 0) {
            $this->command->info('Test rides already exist, skipping.');
            return;
        }

        $rides = [
            [
                'pickup' => 'Pangeran Diponegoro Statue',
                'dest' => 'Semarang State Polytechnic',
                'status' => 'completed',
                'fare' => 8000,
                'distance' => 2.1,
                'duration' => 7,
                'days_ago' => 7,
                'rider_rating' => 5,
                'driver_rating' => 5,
            ],
            [
                'pickup' => 'Muladi Dome - Diponegoro University Convention Center',
                'dest' => 'POLINES TN Canteen',
                'status' => 'completed',
                'fare' => 6000,
                'distance' => 1.2,
                'duration' => 4,
                'days_ago' => 5,
                'rider_rating' => 4,
                'driver_rating' => 4,
            ],
            [
                'pickup' => 'Gedung Kuliah Terpadu POLINES Ir. Ign. Darmojo',
                'dest' => 'Kompleks Rusunawa UNDIP',
                'status' => 'completed',
                'fare' => 10000,
                'distance' => 3.0,
                'duration' => 10,
                'days_ago' => 3,
                'rider_rating' => 5,
                'driver_rating' => null, // rider didn't rate driver back
            ],
            [
                'pickup' => 'Kompleks Rusunawa UNDIP',
                'dest' => 'Rumah Sakit Nasional Diponegoro',
                'status' => 'completed',
                'fare' => 7000,
                'distance' => 1.8,
                'duration' => 6,
                'days_ago' => 0, // recent — so can_rate shows up
                'hours_ago' => 6,
                'rider_rating' => null, // intentionally unrated → can_rate: true
                'driver_rating' => 4,
            ],
            [
                'pickup' => 'Semarang State Polytechnic',
                'dest' => 'Pangeran Diponegoro Statue',
                'status' => 'cancelled',
                'fare' => 9000,
                'distance' => 2.1,
                'duration' => 7,
                'days_ago' => 2,
                'rider_rating' => null,
                'driver_rating' => null,
            ],
        ];

        $ridesCreated = 0;

        foreach ($rides as $r) {
            $pickup = Location::where('name', $r['pickup'])->first();
            $dest = Location::where('name', $r['dest'])->first();

            if (! $pickup || ! $dest) {
                $this->command->warn("Skipping ride: location not found ({$r['pickup']} → {$r['dest']})");
                continue;
            }

            $routeCache = RouteCache::where('origin_location_id', $pickup->id)
                ->where('destination_location_id', $dest->id)
                ->first();

            $createdAt = isset($r['hours_ago'])
                ? now()->subHours($r['hours_ago'])
                : now()->subDays($r['days_ago']);

            $rideRequest = RideRequest::create([
                'rider_id' => $rider->id,
                'pickup_location_id' => $pickup->id,
                'destination_location_id' => $dest->id,
                'estimated_distance_km' => $r['distance'],
                'estimated_duration_minutes' => $r['duration'],
                'estimated_fare_rp' => $r['fare'],
                'passenger_count' => 1,
                'status' => $r['status'],
                'expires_at' => $createdAt->copy()->addMinutes(5),
                'matched_at' => $r['status'] === 'completed' ? $createdAt->copy()->addSeconds(15) : null,
                'current_driver_id' => $r['status'] === 'completed' ? $driver->id : null,
                'route_geometry' => $routeCache?->route_geometry,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $ride = Ride::create([
                'ride_request_id' => $rideRequest->id,
                'rider_id' => $rider->id,
                'driver_id' => $driver->id,
                'pickup_location_id' => $pickup->id,
                'destination_location_id' => $dest->id,
                'status' => $r['status'],
                'passenger_count' => 1,
                'estimated_fare_rp' => $r['fare'],
                'actual_fare_rp' => $r['status'] === 'completed' ? $r['fare'] : null,
                'actual_distance_km' => $r['status'] === 'completed' ? $r['distance'] : null,
                'actual_duration_minutes' => $r['status'] === 'completed' ? $r['duration'] : null,
                'driver_accepted_at' => $createdAt->copy()->addSeconds(10),
                'pickup_time' => $r['status'] === 'completed' ? $createdAt->copy()->addMinutes(5) : null,
                'dropoff_time' => $r['status'] === 'completed' ? $createdAt->copy()->addMinutes(5 + $r['duration']) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Rider → Driver rating
            if ($r['rider_rating']) {
                Rating::create([
                    'ride_id' => $ride->id,
                    'rater_id' => $rider->id,
                    'rated_id' => $driver->id,
                    'rating_type' => 'rider_to_driver',
                    'score' => $r['rider_rating'],
                    'tags' => json_encode($r['rider_rating'] >= 4 ? ['safe_driving', 'friendly'] : []),
                    'feedback' => null,
                ]);
            }

            // Driver → Rider rating
            if ($r['driver_rating']) {
                Rating::create([
                    'ride_id' => $ride->id,
                    'rater_id' => $driver->id,
                    'rated_id' => $rider->id,
                    'rating_type' => 'driver_to_rider',
                    'score' => $r['driver_rating'],
                    'tags' => json_encode([]),
                    'feedback' => null,
                ]);
            }

            $ridesCreated++;
        }

        // Update driver profile ride count
        $completedCount = $rider->riderRides()->where('status', 'completed')->count();
        $driverProfile->update(['total_rides_given' => $completedCount]);

        $this->command->info("Seeded {$ridesCreated} test rides with ratings.");
    }

    private function seedActiveRide(User $rider, User $driver, DriverProfile $driverProfile): void
    {
        if ($rider->riderRides()->where('status', 'in_progress')->exists()) {
            $this->command->info('Active test ride already exists, skipping.');
            return;
        }

        $pickup = Location::where('name', 'Pangeran Diponegoro Statue')->first();
        $dest = Location::where('name', 'Kompleks Rusunawa UNDIP')->first();

        if (! $pickup || ! $dest) {
            $this->command->warn('Skipping active ride: locations not found.');
            return;
        }

        $routeCache = RouteCache::where('origin_location_id', $pickup->id)
            ->where('destination_location_id', $dest->id)
            ->first();

        $acceptedAt = now()->subMinutes(8);

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'estimated_distance_km' => $routeCache?->distance_meters ? round($routeCache->distance_meters / 1000, 2) : 3.0,
            'estimated_duration_minutes' => $routeCache?->duration_minutes ?? 10,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'in_progress',
            'expires_at' => $acceptedAt->copy()->subMinutes(2),
            'matched_at' => $acceptedAt,
            'current_driver_id' => $driver->id,
            'route_geometry' => $routeCache?->route_geometry,
        ]);

        Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'status' => 'in_progress',
            'passenger_count' => 1,
            'estimated_fare_rp' => 8000,
            'driver_accepted_at' => $acceptedAt,
            'pickup_time' => $acceptedAt->copy()->addMinutes(3),
        ]);

        // Set driver as online with location near the route
        $driverProfile->update([
            'went_online_at' => $acceptedAt->copy()->subMinutes(10),
            'last_location_update' => now(),
            'current_location' => new Point(
                $pickup->coordinates->latitude + 0.002, // slightly offset from pickup
                $pickup->coordinates->longitude + 0.001,
                4326
            ),
        ]);

        $this->command->info('Seeded active in-progress ride for test accounts.');
    }

    private function seedCreditTransaction(User $driver, DriverProfile $driverProfile, int $amount): void
    {
        $exists = CreditTransaction::where('driver_id', $driver->id)
            ->where('description', 'Initial credits for test account')
            ->exists();

        if ($exists) {
            return;
        }

        CreditTransaction::create([
            'driver_id' => $driver->id,
            'type' => 'admin_grant',
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'description' => 'Initial credits for test account',
        ]);

        $driverProfile->update([
            'credits_balance' => $amount,
            'credits_total_earned' => $amount,
        ]);
    }
}
