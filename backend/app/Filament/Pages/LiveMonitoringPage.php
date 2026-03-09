<?php

namespace App\Filament\Pages;

use App\Events\RideRequestCancelled;
use App\Events\RideStatusUpdated;
use App\Models\AdminAuditLog;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\MatchingQueueService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LiveMonitoringPage extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static string $view = 'filament.pages.live-monitoring';
    protected static ?string $navigationLabel = 'Live Monitor';
    protected static ?string $navigationGroup = 'Rides';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Live Monitoring';

    public function getPendingRequests(): Collection
    {
        return RideRequest::with(['rider', 'pickupLocation', 'destinationLocation', 'currentDriver'])
            ->whereIn('status', ['pending', 'matched'])
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->get();
    }

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

    public function forceCancelRideRequestAction(): Action
    {
        return Action::make('forceCancelRideRequest')
            ->label('Cancel Request')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form([
                TextInput::make('reason')
                    ->label('Reason (optional)')
                    ->placeholder('e.g. Suspected abuse, test request…'),
            ])
            ->requiresConfirmation()
            ->action(function (array $arguments, array $data): void {
                $rideRequest = RideRequest::findOrFail($arguments['rideRequestId']);

                if (! in_array($rideRequest->status, ['pending', 'matched'])) {
                    $this->sendSuccessNotification('Request is no longer active.');
                    return;
                }

                $currentDriverId = $rideRequest->current_driver_id;

                DB::transaction(function () use ($rideRequest, $data): void {
                    $rideRequest->update([
                        'status'            => 'cancelled',
                        'current_driver_id' => null,
                    ]);
                    AdminAuditLog::create([
                        'admin_id'    => auth()->id(),
                        'action_type' => 'ride_request_cancel',
                        'target_type' => RideRequest::class,
                        'target_id'   => $rideRequest->id,
                        'changes'     => ['status' => ['from' => $rideRequest->getOriginal('status'), 'to' => 'cancelled']],
                        'reason'      => $data['reason'] ?? null,
                        'ip_address'  => request()->ip(),
                        'user_agent'  => request()->userAgent(),
                    ]);
                });

                broadcast(new RideRequestCancelled(
                    $rideRequest->fresh(),
                    'admin',
                    $data['reason'] ?? null,
                    $currentDriverId,
                    true,
                ));

                $this->sendSuccessNotification('Ride request cancelled.');
            });
    }

    public function forceCompleteAction(): Action
    {
        return Action::make('forceComplete')
            ->label('Force Complete')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->form([
                TextInput::make('reason')
                    ->required()
                    ->minLength(10)
                    ->label('Reason (min 10 chars)'),
            ])
            ->requiresConfirmation()
            ->action(function (array $arguments, array $data): void {
                $ride = Ride::with(['pickupLocation', 'destinationLocation'])->findOrFail($arguments['rideId']);
                $previousStatus = $ride->status;
                $driverId = $ride->driver_id;

                DB::transaction(function () use ($ride, $data, $previousStatus): void {
                    $ride->update(['status' => 'completed', 'dropoff_time' => now()]);
                    if ($ride->rideRequest) {
                        $ride->rideRequest->markAsCompleted();
                    }
                    AdminAuditLog::create([
                        'admin_id'    => auth()->id(),
                        'action_type' => 'ride_force_complete',
                        'target_type' => Ride::class,
                        'target_id'   => $ride->id,
                        'changes'     => ['status' => ['from' => $previousStatus, 'to' => 'completed']],
                        'reason'      => $data['reason'],
                        'ip_address'  => request()->ip(),
                        'user_agent'  => request()->userAgent(),
                    ]);
                });

                broadcast(new RideStatusUpdated(
                    $ride->fresh(['pickupLocation', 'destinationLocation']),
                    $previousStatus,
                    'admin',
                    true,
                    $data['reason']
                ));

                if ($driverId) {
                    app(MatchingQueueService::class)->rejoinAfterRide($driverId);
                }

                $this->sendSuccessNotification('Ride marked as completed.');
            });
    }

    public function forceCancelAction(): Action
    {
        return Action::make('forceCancel')
            ->label('Force Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form([
                TextInput::make('reason')
                    ->required()
                    ->minLength(10)
                    ->label('Reason (min 10 chars)'),
            ])
            ->requiresConfirmation()
            ->action(function (array $arguments, array $data): void {
                $ride = Ride::with(['pickupLocation', 'destinationLocation'])->findOrFail($arguments['rideId']);
                $previousStatus = $ride->status;
                $driverId = $ride->driver_id;

                DB::transaction(function () use ($ride, $data, $previousStatus): void {
                    $ride->update(['status' => 'cancelled', 'dropoff_time' => now()]);
                    if ($ride->rideRequest) {
                        $ride->rideRequest->markAsCancelled();
                    }
                    AdminAuditLog::create([
                        'admin_id'    => auth()->id(),
                        'action_type' => 'ride_cancel',
                        'target_type' => Ride::class,
                        'target_id'   => $ride->id,
                        'changes'     => ['status' => ['from' => $previousStatus, 'to' => 'cancelled']],
                        'reason'      => $data['reason'],
                        'ip_address'  => request()->ip(),
                        'user_agent'  => request()->userAgent(),
                    ]);
                });

                broadcast(new RideStatusUpdated(
                    $ride->fresh(['pickupLocation', 'destinationLocation']),
                    $previousStatus,
                    'admin',
                    true,
                    $data['reason']
                ));

                if ($driverId) {
                    app(MatchingQueueService::class)->rejoinAfterRide($driverId);
                }

                $this->sendSuccessNotification('Ride cancelled.');
            });
    }

    private function sendSuccessNotification(string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title($message)
            ->success()
            ->send();
    }
}
