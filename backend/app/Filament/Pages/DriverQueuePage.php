<?php

namespace App\Filament\Pages;

use App\Models\DriverProfile;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class DriverQueuePage extends Page
{
    protected static ?string $navigationGroup = 'Rides';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Driver Queue';

    protected static string $view = 'filament.pages.driver-queue';

    public function getQueuedDrivers(): Collection
    {
        return DriverProfile::with('user')
            ->whereNotNull('queue_joined_at')
            ->orderBy('queue_joined_at', 'asc')
            ->get();
    }

    public function getQueueStats(): array
    {
        $drivers = $this->getQueuedDrivers();

        $avgWaitMinutes = $drivers->isNotEmpty()
            ? $drivers->avg(fn ($d) => now()->diffInMinutes($d->queue_joined_at))
            : 0;

        $inCooldown = $drivers->filter(fn ($d) => $d->isInCooldown())->count();

        return [
            'total' => $drivers->count(),
            'avg_wait_minutes' => round($avgWaitMinutes),
            'in_cooldown' => $inCooldown,
        ];
    }
}
