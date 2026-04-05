<?php

namespace App\Filament\Pages;

use App\Models\DriverProfile;
use App\Models\Ride;
use Filament\Pages\Page;

class LiveMapPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Live Map';
    protected static ?string $navigationGroup = 'Rides';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Live Map';
    protected static string $view = 'filament.pages.live-map';

    public function getMapData(): array
    {
        return [
            'drivers' => $this->getOnlineDrivers(),
            'rides' => $this->getActiveRides(),
            'mapbox_token' => config('services.mapbox.public_token'),
        ];
    }

    private function getOnlineDrivers(): array
    {
        return DriverProfile::query()
            ->whereNotNull('went_online_at')
            ->where('is_verified', true)
            ->whereNotNull('current_location')
            ->with('user')
            ->get()
            ->map(fn (DriverProfile $dp) => [
                'id' => $dp->user_id,
                'name' => $dp->user?->name ?? 'Unknown',
                'lat' => $dp->current_location->latitude,
                'lng' => $dp->current_location->longitude,
                'vehicle_type' => $dp->vehicle_type,
                'in_queue' => $dp->queue_joined_at !== null,
                'credits' => $dp->credits_balance,
                'rating' => $dp->rating_average,
                'online_since' => $dp->went_online_at->toIso8601String(),
            ])
            ->values()
            ->toArray();
    }

    private function getActiveRides(): array
    {
        $activeStatuses = ['matched', 'accepted', 'driver_arrived', 'in_progress'];

        return Ride::query()
            ->whereIn('status', $activeStatuses)
            ->with(['rider', 'driver', 'pickupLocation', 'destinationLocation', 'rideRequest'])
            ->get()
            ->map(fn (Ride $ride) => [
                'id' => $ride->id,
                'rider_name' => $ride->rider?->name ?? 'Unknown',
                'driver_name' => $ride->driver?->name ?? 'Unknown',
                'status' => $ride->status,
                'pickup' => [
                    'lat' => $ride->pickupLocation?->coordinates?->latitude,
                    'lng' => $ride->pickupLocation?->coordinates?->longitude,
                    'name' => $ride->pickupLocation?->name ?? '?',
                ],
                'destination' => [
                    'lat' => $ride->destinationLocation?->coordinates?->latitude,
                    'lng' => $ride->destinationLocation?->coordinates?->longitude,
                    'name' => $ride->destinationLocation?->name ?? '?',
                ],
                'route_geometry' => $ride->rideRequest?->route_geometry,
                'fare' => $ride->estimated_fare_rp,
                'created_at' => $ride->created_at->toIso8601String(),
            ])
            ->values()
            ->toArray();
    }
}
