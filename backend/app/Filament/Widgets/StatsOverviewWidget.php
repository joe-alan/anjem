<?php

namespace App\Filament\Widgets;

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $revenue30d = Ride::where('status', 'completed')
            ->where('dropoff_time', '>=', now()->subDays(30))
            ->sum('actual_fare_rp');

        return [
            Stat::make('Total Users', User::count())
                ->description('Riders + drivers')
                ->icon('heroicon-o-users')
                ->color('gray'),

            Stat::make('Online Drivers', DriverProfile::whereNotNull('went_online_at')->count())
                ->description('Right now')
                ->icon('heroicon-o-truck')
                ->color('success'),

            Stat::make('Active Rides', Ride::whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])->count())
                ->description('In progress')
                ->icon('heroicon-o-map-pin')
                ->color('warning'),

            Stat::make('Revenue (30d)', 'Rp ' . number_format($revenue30d, 0, ',', '.'))
                ->description('Last 30 days')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
