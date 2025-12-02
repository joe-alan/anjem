<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * EssentialLocationsSeeder populates the database with key campus locations
 *
 * Loads locations from new_locations.csv (Universitas Diponegoro campus)
 */
class EssentialLocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // IMPORTANT: Only clear locations table, no other tables
        if (app()->environment('local')) {
            Location::truncate();
            $this->command->info('✓ Cleared existing locations (ONLY locations table)');
        }

        // Seed locations from CSV
        $this->seedFromCsv();

        $this->command->info('✓ Essential locations seeded successfully!');
    }

    /**
     * Seed locations from CSV file
     */
    private function seedFromCsv(): void
    {
        // CSV is in project root, one level up from backend directory
        $csvPath = base_path('../backend/database/seeders/new_locations.csv');

        if (! file_exists($csvPath)) {
            $this->command->error('CSV file not found: '.$csvPath);

            return;
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
                'radius_m' => 100, // Database default
                'is_beacon' => false, // Regular destinations, not pickup beacons
                'is_active' => true,
                'usage_count' => 0,
                'description' => null, // Not in CSV, leave empty
                'metadata' => [
                    'priority' => 'high',
                    'source' => 'csv_import',
                    'campus' => 'Universitas Diponegoro',
                    'imported_at' => now()->toISOString(),
                ],
            ]);

            $count++;
        }

        $this->command->info("✓ Seeded {$count} locations from CSV (Universitas Diponegoro)");
    }
}
