<?php

namespace App\Filament\Pages;

use App\Models\DispatchAttempt;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class DispatchTimelinePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Dispatch Timeline';
    protected static ?string $navigationGroup = 'Rides';
    protected static ?int $navigationSort = 6;
    protected static ?string $title = 'Dispatch Timeline';
    protected static string $view = 'filament.pages.dispatch-timeline';

    public function getRecentDispatches(): Collection
    {
        return DispatchAttempt::query()
            ->with([
                'rideRequest.rider',
                'rideRequest.pickupLocation',
                'rideRequest.destinationLocation',
                'driver',
            ])
            ->latest('dispatched_at')
            ->limit(100)
            ->get();
    }
}
