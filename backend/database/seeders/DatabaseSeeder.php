<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use MatanYadaev\EloquentSpatial\Objects\Point;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting Anjem database seeding with Firebase integration...');

        // Clear existing locations in local environment
        if (app()->environment('local')) {
            Location::truncate();
            $this->command->info('✓ Cleared existing locations (ONLY locations table)');
        }

        // Seed campus locations from CSV
        $this->command->info('📍 Seeding campus locations from CSV...');
        $locationCount = $this->seedLocationsFromCsv();
        $this->command->info("✓ Seeded {$locationCount} locations from CSV (Universitas Diponegoro)");

        // Create test users with different types
        $this->command->info('Creating test users...');

        // Test accounts
        $testRider = \App\Models\User::factory()->rider()->create([
            'name' => 'Test Rider',
            'email' => 'rider@anjem.test',
            'firebase_uid' => 'test_rider_firebase_uid',
        ]);

        $testDriver = \App\Models\User::factory()->driver()->create([
            'name' => 'Test Driver',
            'email' => 'driver@anjem.test',
            'firebase_uid' => 'test_driver_firebase_uid',
        ]);

        $testBoth = \App\Models\User::factory()->both()->create([
            'name' => 'Test Both',
            'email' => 'both@anjem.test',
            'firebase_uid' => 'test_both_firebase_uid',
        ]);

        // Create additional users with different types
        \App\Models\User::factory()->rider()->count(5)->create();
        \App\Models\User::factory()->driver()->count(3)->create();
        \App\Models\User::factory()->both()->count(2)->create();

        // Create some Firebase-authenticated users
        \App\Models\User::factory()->firebaseAuth()->count(3)->create();

        $this->command->info('✅ Database seeding completed!');
        $this->command->info('📊 Created:');
        $this->command->info("   • {$locationCount} campus locations (Universitas Diponegoro)");
        $this->command->info('   • 16 users total (5 riders, 4 drivers, 3 both, 3 Firebase, 1 test each)');
        $this->command->info('');
        $this->command->info('🔑 Test accounts:');
        $this->command->info('   • Rider: rider@anjem.test');
        $this->command->info('   • Driver: driver@anjem.test');
        $this->command->info('   • Both: both@anjem.test');
        $this->command->info('   • Password: password (for non-Firebase users)');
        $this->command->info('');
        $this->command->info('🚀 Ready for Firebase authentication and mobile app integration!');
    }

    /**
     * Seed locations from CSV file
     */
    private function seedLocationsFromCsv(): int
    {
        $csvPath = base_path('database/seeders/new_locations.csv');

        if (! file_exists($csvPath)) {
            $this->command->error('CSV file not found: '.$csvPath);
            return 0;
        }

        $csvData = array_map('str_getcsv', file($csvPath));
        $headers = array_shift($csvData); // Remove header row

        $count = 0;

        foreach ($csvData as $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Map CSV columns: name, address, latitude, longitude
            $name = trim($row[0] ?? '');
            $address = trim($row[1] ?? '');
            $latitude = floatval($row[2] ?? 0);
            $longitude = floatval($row[3] ?? 0);

            // Skip if essential data is missing
            if (empty($name) || $latitude === 0.0 || $longitude === 0.0) {
                continue;
            }

            Location::create([
                'name' => $name,
                'address' => $address,
                'coordinates' => new Point($latitude, $longitude, 4326),
                'radius_m' => 100,
                'is_beacon' => false,
                'is_active' => true,
                'usage_count' => 0,
                'description' => null,
                'metadata' => [
                    'priority' => 'high',
                    'source' => 'csv_import',
                    'campus' => 'Universitas Diponegoro',
                    'imported_at' => now()->toISOString(),
                ],
            ]);

            $count++;
        }

        return $count;
    }
}
