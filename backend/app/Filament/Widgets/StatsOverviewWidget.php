<?php

namespace App\Filament\Widgets;

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeStatuses = ['matched', 'accepted', 'driver_arrived', 'in_progress'];

        $cashFlow30d = Ride::where('status', 'completed')
            ->where('dropoff_time', '>=', now()->subDays(30))
            ->sum('actual_fare_rp');

        $completedToday = Ride::where('status', 'completed')
            ->whereDate('dropoff_time', today())
            ->count();

        $cancelledToday = Ride::where('status', 'cancelled')
            ->whereDate('updated_at', today())
            ->count();

        $pendingRequests = RideRequest::where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();

        $totalDrivers = DriverProfile::count();
        $verifiedDrivers = DriverProfile::where('is_verified', true)->count();
        $onlineDrivers = DriverProfile::whereNotNull('went_online_at')->count();
        $inQueue = DriverProfile::whereNotNull('queue_joined_at')->count();

        $kycPending = DriverProfile::where('is_verified', false)->count();

        $completedThisWeek = Ride::where('status', 'completed')
            ->where('dropoff_time', '>=', now()->startOfWeek())
            ->count();

        return [
            Stat::make('Total Users', User::count())
                ->description('Riders + drivers')
                ->icon('heroicon-o-users')
                ->color('gray'),

            Stat::make('Online Drivers', $onlineDrivers)
                ->description($inQueue . ' in queue · ' . $verifiedDrivers . '/' . $totalDrivers . ' verified')
                ->icon('heroicon-o-truck')
                ->color('success'),

            Stat::make('Active Rides', Ride::whereIn('status', $activeStatuses)->count())
                ->description($pendingRequests . ' pending requests')
                ->icon('heroicon-o-map-pin')
                ->color('warning'),

            Stat::make('Cash Flow (30d)', 'Rp ' . number_format($cashFlow30d, 0, ',', '.'))
                ->description('Last 30 days')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Rides Today', $completedToday)
                ->description($cancelledToday . ' cancelled · ' . $completedThisWeek . ' this week')
                ->icon('heroicon-o-check-circle')
                ->color('info'),

            Stat::make('KYC Pending', $kycPending)
                ->description('Drivers awaiting approval')
                ->icon('heroicon-o-identification')
                ->color($kycPending > 0 ? 'danger' : 'success')
                ->extraAttributes([
                    'class' => $kycPending > 0
                        ? 'ring-2 ring-danger-500 dark:ring-danger-400 bg-danger-50 dark:bg-danger-950'
                        : '',
                ])
                ->url(route('filament.admin.resources.kyc.index')),
        ];
    }
}
