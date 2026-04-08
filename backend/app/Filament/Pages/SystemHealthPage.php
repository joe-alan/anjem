<?php

namespace App\Filament\Pages;

use App\Models\DriverProfile;
use App\Models\FailedJob;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\RouteCache;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class SystemHealthPage extends Page
{
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationLabel = 'System Health';
    protected static string $view = 'filament.pages.system-health';

    public function getServiceChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'reverb' => $this->checkReverb(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];
    }

    public function getApplicationMetrics(): array
    {
        $activeStatuses = ['matched', 'accepted', 'driver_arrived', 'in_progress'];

        $completedToday = Ride::where('status', 'completed')->whereDate('dropoff_time', today())->count();
        $cancelledToday = Ride::where('status', 'cancelled')->whereDate('updated_at', today())->count();
        $completedTotal = Ride::where('status', 'completed')->count();

        return [
            'pending_requests' => RideRequest::where('status', 'pending')->where('expires_at', '>', now())->count(),
            'active_rides' => Ride::whereIn('status', $activeStatuses)->count(),
            'online_drivers' => DriverProfile::whereNotNull('went_online_at')->count(),
            'in_queue' => DriverProfile::whereNotNull('queue_joined_at')->count(),
            'completed_today' => $completedToday,
            'cancelled_today' => $cancelledToday,
            'completed_total' => $completedTotal,
            'total_users' => User::count(),
            'total_drivers' => DriverProfile::count(),
            'verified_drivers' => DriverProfile::where('is_verified', true)->count(),
        ];
    }

    public function getQueueHealth(): array
    {
        $failedCount = FailedJob::count();
        $recentFailed = FailedJob::where('failed_at', '>=', now()->subHours(24))->count();

        return [
            'failed_total' => $failedCount,
            'failed_24h' => $recentFailed,
        ];
    }

    public function getCacheHealth(): array
    {
        $totalRoutes = RouteCache::count();
        $freshRoutes = RouteCache::where('last_fetched_at', '>=', now()->subDays(7))->count();
        $staleRoutes = $totalRoutes - $freshRoutes;

        return [
            'total_routes' => $totalRoutes,
            'fresh_routes' => $freshRoutes,
            'stale_routes' => $staleRoutes,
        ];
    }

    public function getReverbChannels(): array
    {
        $appId = env('REVERB_APP_ID');

        try {
            $response = Http::timeout(2)->get("http://127.0.0.1:8080/apps/{$appId}/channels");

            if ($response->successful()) {
                return $response->json('channels', []);
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000);

            return ['status' => 'ok', 'latency' => $ms . 'ms'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'latency' => '-', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Cache::store('redis')->get('health-check');
            $ms = round((microtime(true) - $start) * 1000);

            return ['status' => 'ok', 'latency' => $ms . 'ms'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'latency' => '-', 'error' => $e->getMessage()];
        }
    }

    private function checkReverb(): array
    {
        try {
            $appId = env('REVERB_APP_ID');
            $start = microtime(true);
            $response = Http::timeout(2)->get("http://127.0.0.1:8080/apps/{$appId}/channels");
            $ms = round((microtime(true) - $start) * 1000);

            return [
                'status' => $response->successful() ? 'ok' : 'down',
                'latency' => $ms . 'ms',
                'channels' => $response->successful() ? count($response->json('channels', [])) : 0,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'latency' => '-', 'channels' => 0];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = config('filesystems.default');
            $start = microtime(true);
            app('filesystem')->disk($disk)->exists('health-check.txt');
            $ms = round((microtime(true) - $start) * 1000);

            return ['status' => 'ok', 'latency' => $ms . 'ms', 'disk' => $disk];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'latency' => '-', 'disk' => config('filesystems.default')];
        }
    }

    private function checkQueue(): array
    {
        $failedRecent = FailedJob::where('failed_at', '>=', now()->subHours(1))->count();

        return [
            'status' => $failedRecent === 0 ? 'ok' : 'degraded',
            'failed_1h' => $failedRecent,
        ];
    }
}
