<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * EssentialLocationsSeeder populates the database with key campus locations
 *
 * Includes beacons (pickup points) and popular destinations for Universitas Indonesia campus
 */
class EssentialLocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing locations if in development
        if (app()->environment('local')) {
            Location::truncate();
            $this->command->info('Cleared existing locations');
        }

        // Seed beacon locations (pickup points)
        $this->seedBeacons();

        // Seed popular destinations
        $this->seedDestinations();

        $this->command->info('Essential locations seeded successfully!');
    }

    /**
     * Seed beacon locations (official pickup points)
     */
    private function seedBeacons(): void
    {
        $beacons = [
            // Main Gates
            [
                'name' => 'Gerbang Utama UI (Gate 1)',
                'address' => 'Jl. Margonda Raya, Depok',
                'latitude' => -6.3615,
                'longitude' => 106.8242,
                'description' => 'Main entrance of Universitas Indonesia from Margonda Road',
                'capacity' => 15,
                'category' => 'gate',
                'priority' => 'high',
            ],
            [
                'name' => 'Gerbang UI Kukusan',
                'address' => 'Jl. Akses UI, Kukusan, Depok',
                'latitude' => -6.3680,
                'longitude' => 106.8310,
                'description' => 'Secondary entrance from Kukusan area',
                'capacity' => 10,
                'category' => 'gate',
                'priority' => 'medium',
            ],
            [
                'name' => 'Gerbang UI Pondok Cina',
                'address' => 'Jl. Margonda Raya, Pondok Cina',
                'latitude' => -6.3705,
                'longitude' => 106.8350,
                'description' => 'Entrance near Pondok Cina station',
                'capacity' => 12,
                'category' => 'gate',
                'priority' => 'high',
            ],
            [
                'name' => 'Gate UI Kalisari',
                'address' => 'Jl. Lingkar Kampus Raya, Depok',
                'latitude' => -6.3550,
                'longitude' => 106.8180,
                'description' => 'Entrance from Kalisari area',
                'capacity' => 8,
                'category' => 'gate',
                'priority' => 'low',
            ],

            // Faculty Buildings
            [
                'name' => 'Fakultas Teknik (FT UI)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3605,
                'longitude' => 106.8271,
                'description' => 'Engineering Faculty building complex',
                'capacity' => 10,
                'category' => 'faculty',
                'priority' => 'high',
            ],
            [
                'name' => 'Fakultas Ilmu Komputer (Fasilkom UI)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3622,
                'longitude' => 106.8263,
                'description' => 'Computer Science Faculty',
                'capacity' => 10,
                'category' => 'faculty',
                'priority' => 'high',
            ],
            [
                'name' => 'Fakultas Ekonomi dan Bisnis (FEB UI)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3640,
                'longitude' => 106.8290,
                'description' => 'Economics and Business Faculty',
                'capacity' => 10,
                'category' => 'faculty',
                'priority' => 'high',
            ],
            [
                'name' => 'Fakultas Hukum (FH UI)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3590,
                'longitude' => 106.8285,
                'description' => 'Law Faculty',
                'capacity' => 8,
                'category' => 'faculty',
                'priority' => 'medium',
            ],
            [
                'name' => 'Fakultas Psikologi UI',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3660,
                'longitude' => 106.8305,
                'description' => 'Psychology Faculty',
                'capacity' => 8,
                'category' => 'faculty',
                'priority' => 'medium',
            ],

            // Student Centers & Facilities
            [
                'name' => 'Balairung UI',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3625,
                'longitude' => 106.8280,
                'description' => 'Main administrative building and student center',
                'capacity' => 12,
                'category' => 'administrative',
                'priority' => 'high',
            ],
            [
                'name' => 'Stasiun UI',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3698,
                'longitude' => 106.8314,
                'description' => 'UI commuter train station',
                'capacity' => 15,
                'category' => 'transportation',
                'priority' => 'high',
            ],
            [
                'name' => 'Terminal Bis Kuning UI',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3612,
                'longitude' => 106.8255,
                'description' => 'Yellow bus terminal for campus transportation',
                'capacity' => 10,
                'category' => 'transportation',
                'priority' => 'medium',
            ],
        ];

        foreach ($beacons as $beacon) {
            Location::create([
                'name' => $beacon['name'],
                'address' => $beacon['address'],
                'coordinates' => new Point($beacon['latitude'], $beacon['longitude'], 4326),
                'radius_m' => 50, // 50m radius for beacon coverage
                'is_beacon' => true,
                'is_active' => true,
                'beacon_capacity' => $beacon['capacity'],
                'current_queue_size' => 0,
                'description' => $beacon['description'],
                'usage_count' => 0,
                'metadata' => [
                    'category' => $beacon['category'],
                    'priority' => $beacon['priority'],
                    'source' => 'manual_seed',
                ],
            ]);
        }

        $this->command->info('✓ Seeded ' . count($beacons) . ' beacon locations');
    }

    /**
     * Seed popular destination locations
     */
    private function seedDestinations(): void
    {
        $destinations = [
            // Canteens & Cafes
            [
                'name' => 'Kantin Fasilkom',
                'address' => 'Fakultas Ilmu Komputer UI, Depok',
                'latitude' => -6.3618,
                'longitude' => 106.8268,
                'description' => 'Popular canteen at Computer Science faculty',
                'category' => 'canteen',
            ],
            [
                'name' => 'Kantin FIK UI',
                'address' => 'Fakultas Ilmu Keperawatan UI, Depok',
                'latitude' => -6.3575,
                'longitude' => 106.8295,
                'description' => 'Affordable canteen near nursing faculty',
                'category' => 'canteen',
            ],
            [
                'name' => 'Kantin Teknik (Kantin Mesin)',
                'address' => 'Fakultas Teknik UI, Depok',
                'latitude' => -6.3602,
                'longitude' => 106.8275,
                'description' => 'Large engineering faculty canteen',
                'category' => 'canteen',
            ],
            [
                'name' => 'Warung Bu Yani',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3630,
                'longitude' => 106.8285,
                'description' => 'Famous affordable food stall',
                'category' => 'canteen',
            ],
            [
                'name' => 'Danau UI Food Court',
                'address' => 'Danau Kenanga UI, Depok',
                'latitude' => -6.3595,
                'longitude' => 106.8300,
                'description' => 'Food court with lake view',
                'category' => 'canteen',
            ],

            // Libraries & Study Spaces
            [
                'name' => 'Perpustakaan Pusat UI (Crystal of Knowledge)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3642,
                'longitude' => 106.8278,
                'description' => 'Main university library',
                'category' => 'library',
            ],
            [
                'name' => 'Perpustakaan Fakultas Teknik',
                'address' => 'Fakultas Teknik UI, Depok',
                'latitude' => -6.3608,
                'longitude' => 106.8274,
                'description' => 'Engineering faculty library',
                'category' => 'library',
            ],

            // Sports & Recreation
            [
                'name' => 'GOR UI (Gymnasium)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3582,
                'longitude' => 106.8248,
                'description' => 'Main sports hall',
                'category' => 'sports',
            ],
            [
                'name' => 'Stadion UI',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3570,
                'longitude' => 106.8235,
                'description' => 'University stadium and running track',
                'category' => 'sports',
            ],

            // Dormitories
            [
                'name' => 'Asrama UI (Wisma Makara)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3650,
                'longitude' => 106.8320,
                'description' => 'Main student dormitory',
                'category' => 'dormitory',
            ],
            [
                'name' => 'Asrama UI Blok A',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3655,
                'longitude' => 106.8325,
                'description' => 'Student dormitory block A',
                'category' => 'dormitory',
            ],

            // Medical & Healthcare
            [
                'name' => 'RSKGM UI (Klinik Gigi)',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3595,
                'longitude' => 106.8270,
                'description' => 'Dental hospital and clinic',
                'category' => 'healthcare',
            ],
            [
                'name' => 'Klinik UI',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3620,
                'longitude' => 106.8278,
                'description' => 'Campus health clinic',
                'category' => 'healthcare',
            ],

            // Shopping & Services
            [
                'name' => 'Minimarket FEB',
                'address' => 'Fakultas Ekonomi UI, Depok',
                'latitude' => -6.3642,
                'longitude' => 106.8292,
                'description' => 'Convenience store at economics faculty',
                'category' => 'shopping',
            ],
            [
                'name' => 'ATM Center UI',
                'address' => 'Kampus UI Depok',
                'latitude' => -6.3628,
                'longitude' => 106.8282,
                'description' => 'Multiple ATM machines',
                'category' => 'services',
            ],
        ];

        foreach ($destinations as $destination) {
            Location::create([
                'name' => $destination['name'],
                'address' => $destination['address'],
                'coordinates' => new Point($destination['latitude'], $destination['longitude'], 4326),
                'radius_m' => 25, // Default 25m radius for destination marker area
                'is_beacon' => false,
                'is_active' => true,
                // beacon_capacity and current_queue_size will use database defaults (10 and 0)
                'description' => $destination['description'],
                'usage_count' => 0,
                'metadata' => [
                    'category' => $destination['category'],
                    'source' => 'manual_seed',
                ],
            ]);
        }

        $this->command->info('✓ Seeded ' . count($destinations) . ' destination locations');
    }
}
