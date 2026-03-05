<?php

namespace App\Filament\Pages;

use App\Models\Ride;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LiveMonitoringPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static string $view = 'filament.pages.live-monitoring';
    protected static ?string $navigationLabel = 'Live Monitor';
    protected static ?string $navigationGroup = 'Rides';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Live Monitoring';

    public function getActiveRides(): Collection
    {
        return Ride::with(['rider', 'driver', 'pickupLocation', 'destinationLocation'])
            ->whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])
            ->orderBy('updated_at', 'asc')
            ->get();
    }

    public function getOnlineDrivers(): Collection
    {
        return User::with('driverProfile')
            ->whereHas('driverProfile', fn($q) => $q->whereNotNull('went_online_at'))
            ->get();
    }
}
