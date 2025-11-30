<?php

namespace App\Console\Commands;

use App\Services\RouteCacheService;
use Illuminate\Console\Command;

class TestRouteCaching extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:route-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test route caching functionality and measure performance improvement';

    private RouteCacheService $routeCacheService;

    public function __construct(RouteCacheService $routeCacheService)
    {
        parent::__construct();
        $this->routeCacheService = $routeCacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Route Caching System');
        $this->newLine();

        // Test between two popular campus locations
        $originId = 1;
        $destId = 5;
        $originLat = -6.3615;
        $originLng = 106.8242;
        $destLat = -6.3705;
        $destLng = 106.8321;

        // First call - should be cache MISS
        $this->info('📍 Call 1: Fetching route (expected: cache MISS)');
        $start = microtime(true);
        $route1 = $this->routeCacheService->getOrFetchRoute(
            $originId,
            $destId,
            $originLat,
            $originLng,
            $destLat,
            $destLng
        );
        $time1 = round((microtime(true) - $start) * 1000);

        $this->line("   ⏱️  Time: {$time1}ms");
        $this->line('   📏 Distance: '.$route1['distance_meters'].'m');
        $this->line('   ⏰ Duration: '.$route1['duration_minutes'].'min');
        $this->line('   💾 Cached: '.($route1['cached'] ? 'YES' : 'NO'));
        $this->newLine();

        // Second call - should be cache HIT
        $this->info('📍 Call 2: Fetching same route (expected: cache HIT)');
        $start = microtime(true);
        $route2 = $this->routeCacheService->getOrFetchRoute(
            $originId,
            $destId,
            $originLat,
            $originLng,
            $destLat,
            $destLng
        );
        $time2 = round((microtime(true) - $start) * 1000);

        $this->line("   ⏱️  Time: {$time2}ms");
        $this->line('   📏 Distance: '.$route2['distance_meters'].'m');
        $this->line('   ⏰ Duration: '.$route2['duration_minutes'].'min');
        $this->line('   💾 Cached: '.($route2['cached'] ? 'YES' : 'NO'));
        $this->newLine();

        // Calculate improvement
        $improvement = max($time2, 1) > 0 ? round($time1 / max($time2, 1), 1) : 0;
        $this->info("⚡ Speed Improvement: {$improvement}x faster");
        $this->newLine();

        // Display cache statistics
        $this->info('📊 Cache Statistics:');
        $stats = $this->routeCacheService->getCacheStats();

        $headers = ['Metric', 'Value'];
        $rows = [
            ['Total Cached Routes', $stats['total_cached_routes']],
            ['Fresh Routes (< 7 days)', $stats['fresh_routes']],
            ['Stale Routes (> 7 days)', $stats['stale_routes']],
            ['Total Cache Hits', $stats['total_cache_hits']],
            ['Avg Reuse Per Route', $stats['avg_reuse_per_route']],
            ['Cache Hit Rate', $stats['cache_hit_rate'].'%'],
        ];

        $this->table($headers, $rows);

        // Show popular routes if any
        $popularRoutes = $this->routeCacheService->getPopularRoutes(5);
        if (! empty($popularRoutes)) {
            $this->newLine();
            $this->info('🔥 Top 5 Popular Routes:');
            $headers = ['Origin', 'Destination', 'Hits', 'Distance (km)', 'Duration (min)'];
            $rows = array_map(function ($route) {
                return [
                    $route['origin'],
                    $route['destination'],
                    $route['fetch_count'],
                    $route['distance_km'],
                    $route['duration_min'],
                ];
            }, $popularRoutes);

            $this->table($headers, $rows);
        }

        $this->newLine();
        $this->info('✅ Route caching test completed successfully!');

        return Command::SUCCESS;
    }
}
