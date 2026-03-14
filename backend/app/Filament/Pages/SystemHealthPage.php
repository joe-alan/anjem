<?php

namespace App\Filament\Pages;

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideRequest;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class SystemHealthPage extends Page
{
    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'System Health';

    protected static string $view = 'filament.pages.system-health';

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

    public function getApplicationMetrics(): array
    {
        return [
            'pending_requests' => RideRequest::whereIn('status', ['pending', 'matched'])->count(),
            'active_rides' => Ride::whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])->count(),
            'online_drivers' => DriverProfile::whereNotNull('went_online_at')->count(),
        ];
    }
}
