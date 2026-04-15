<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\RouteCache;
use Illuminate\Database\Seeder;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        $this->seedLocations();
        $this->seedRouteCaches();
        $this->call(TestAccountSeeder::class);
    }

    private function seedLocations(): void
    {
        $jsonPath = base_path('database/seeders/prod_locations.json');

        if (! file_exists($jsonPath)) {
            $this->command->error('Location data not found: ' . $jsonPath);
            return;
        }

        $locations = json_decode(file_get_contents($jsonPath), true);
        $count = 0;

        foreach ($locations as $loc) {
            if (empty($loc['name']) || empty($loc['latitude']) || empty($loc['longitude'])) {
                continue;
            }

            Location::firstOrCreate(
                ['name' => $loc['name']],
                [
                    'address' => $loc['address'] ?? null,
                    'coordinates' => new Point($loc['latitude'], $loc['longitude'], 4326),
                    'radius_m' => $loc['radius_m'] ?? 100,
                    'is_beacon' => false,
                    'is_active' => true,
                    'usage_count' => 0,
                    'description' => $loc['description'] ?? null,
                ]
            );

            $count++;
        }

        $this->command->info("Seeded {$count} locations (usage counts reset to 0)");
    }

    private function seedRouteCaches(): void
    {
        $jsonPath = base_path('database/seeders/prod_route_caches.json');

        if (! file_exists($jsonPath)) {
            $this->command->error('Route cache data not found: ' . $jsonPath);
            return;
        }

        $routes = json_decode(file_get_contents($jsonPath), true);
        $count = 0;

        foreach ($routes as $route) {
            $origin = Location::where('name', $route['origin_name'])->first();
            $destination = Location::where('name', $route['destination_name'])->first();

            if (! $origin || ! $destination) {
                continue;
            }

            RouteCache::firstOrCreate(
                [
                    'origin_location_id' => $origin->id,
                    'destination_location_id' => $destination->id,
                    'profile' => $route['profile'] ?? 'driving',
                ],
                [
                    'route_geometry' => $route['route_geometry'],
                    'distance_meters' => $route['distance_meters'],
                    'duration_minutes' => $route['duration_minutes'],
                    'fetch_count' => 0,
                    'last_fetched_at' => now(),
                ]
            );

            $count++;
        }

        $this->command->info("Seeded {$count} route caches (fetch counts reset to 0)");
    }
}
