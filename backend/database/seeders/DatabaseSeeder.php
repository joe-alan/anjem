<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting Anjem database seeding with Firebase integration...');

        // Create campus beacon locations first
        $this->command->info('Creating campus beacon locations...');

        $beacons = [
            ['Main Gate', 'Universitas Indonesia Main Entrance', -6.3589, 106.8264],
            ['Library', 'Central Library Building', -6.3605, 106.8271],
            ['Student Center', 'Balairung UI', -6.3612, 106.8285],
            ['Engineering Faculty', 'Faculty of Engineering', -6.3595, 106.8295],
            ['Medical Faculty', 'FKUI Building', -6.3625, 106.8305],
        ];

        foreach ($beacons as [$name, $address, $lat, $lng]) {
            \App\Models\Location::create([
                'name' => $name,
                'address' => $address,
                'coordinates' => \Illuminate\Support\Facades\DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)"),
                'radius_m' => 100,
                'is_beacon' => true,
                'is_active' => true,
            ]);
        }

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
        $this->command->info('   • 5 campus beacon locations');
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
}
