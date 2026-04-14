<?php

namespace App\Filament\Pages;

use App\Events\DriverOnlineStatusChanged;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\MatchingQueueService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
            'avg_wait_minutes' => (int) round($avgWaitMinutes),
            'in_cooldown' => $inCooldown,
        ];
    }

    public function kickDriver(int $driverId): void
    {
        $user = User::find($driverId);
        if (! $user) {
            Notification::make()->title('Driver not found')->danger()->send();
            return;
        }

        $profile = DriverProfile::where('user_id', $driverId)->first();
        if (! $profile || ! $profile->isInQueue()) {
            Notification::make()->title('Driver is not in queue')->warning()->send();
            return;
        }

        app(MatchingQueueService::class)->removeFromQueue($driverId);
        $profile->update(['went_online_at' => null]);
        broadcast(new DriverOnlineStatusChanged($user, false, null, null, 'admin_kick'));

        Log::info('Admin kicked driver from queue', [
            'driver_id' => $driverId,
            'admin_id' => auth()->id(),
        ]);

        Notification::make()
            ->title("Kicked {$user->name} from queue")
            ->success()
            ->send();
    }
}
