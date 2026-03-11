<?php
namespace App\Filament\Resources;

use App\Events\RideStatusUpdated;
use App\Models\AdminAuditLog;
use App\Models\Ride;
use App\Services\MatchingQueueService;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\RideResource\Pages;

class RideResource extends Resource
{
    protected static ?string $model = Ride::class;
    protected static ?string $navigationGroup = 'Rides';
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $modelLabel = 'Ride';
    protected static ?string $pluralModelLabel = 'Rides';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        $activeStatuses = ['matched', 'accepted', 'driver_arrived', 'in_progress'];

        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('rider.name')->label('Rider')->searchable(),
                TextColumn::make('driver.name')->label('Driver')->searchable(),
                TextColumn::make('route')->label('Route')
                    ->state(fn(Ride $record): string =>
                        ($record->pickupLocation?->name ?? '?') . ' → ' . ($record->destinationLocation?->name ?? '?')
                    ),
                TextColumn::make('status')->badge()
                    ->color(fn(string $state): string => match($state) {
                        'matched'        => 'gray',
                        'accepted'       => 'info',
                        'driver_arrived' => 'warning',
                        'in_progress'    => 'primary',
                        'completed'      => 'success',
                        'cancelled'      => 'danger',
                        default          => 'gray',
                    }),
                TextColumn::make('actual_fare_rp')->label('Fare')
                    ->formatStateUsing(fn($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                TextColumn::make('actual_duration_minutes')->label('Duration')->suffix(' min')->placeholder('-'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'matched'        => 'Matched',
                        'accepted'       => 'Accepted',
                        'driver_arrived' => 'Driver Arrived',
                        'in_progress'    => 'In Progress',
                        'completed'      => 'Completed',
                        'cancelled'      => 'Cancelled',
                    ])
                    ->multiple(),
                Filter::make('stuck')
                    ->label('Stuck Rides (> 2h)')
                    ->query(fn(Builder $query): Builder => $query
                        ->whereIn('status', $activeStatuses)
                        ->where('updated_at', '<', now()->subHours(2))
                    ),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Action::make('force_complete')
                    ->label('Force Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Ride $record): bool => in_array($record->status, $activeStatuses))
                    ->form([TextInput::make('reason')->required()->minLength(10)->label('Reason (min 10 chars)')])
                    ->requiresConfirmation()
                    ->action(function (Ride $record, array $data): void {
                        $previousStatus = $record->status;
                        $driverId = $record->driver_id;
                        DB::transaction(function () use ($record, $data): void {
                            $record->update(['status' => 'completed', 'dropoff_time' => now()]);
                            if ($record->rideRequest) {
                                $record->rideRequest->markAsCompleted();
                            }
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'ride_force_complete',
                                'target_type' => Ride::class,
                                'target_id'   => $record->id,
                                'changes'     => ['status' => ['from' => $previousStatus, 'to' => 'completed']],
                                'reason'      => $data['reason'],
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                        broadcast(new RideStatusUpdated(
                            $record->fresh(['pickupLocation', 'destinationLocation']),
                            $previousStatus,
                            'admin',
                            true,
                            $data['reason']
                        ));
                        if ($driverId) {
                            app(MatchingQueueService::class)->rejoinAfterRide($driverId);
                        }
                    })
                    ->successNotificationTitle('Ride marked as completed'),

                Action::make('force_cancel')
                    ->label('Force Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Ride $record): bool => in_array($record->status, $activeStatuses))
                    ->form([TextInput::make('reason')->required()->minLength(10)->label('Reason (min 10 chars)')])
                    ->requiresConfirmation()
                    ->action(function (Ride $record, array $data): void {
                        $previousStatus = $record->status;
                        $driverId = $record->driver_id;
                        DB::transaction(function () use ($record, $data): void {
                            $record->update(['status' => 'cancelled', 'dropoff_time' => now()]);
                            if ($record->rideRequest) {
                                $record->rideRequest->markAsCancelled();
                            }
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'ride_cancel',
                                'target_type' => Ride::class,
                                'target_id'   => $record->id,
                                'changes'     => ['status' => ['from' => $previousStatus, 'to' => 'cancelled']],
                                'reason'      => $data['reason'],
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                        broadcast(new RideStatusUpdated(
                            $record->fresh(['pickupLocation', 'destinationLocation']),
                            $previousStatus,
                            'admin',
                            true,
                            $data['reason']
                        ));
                        if ($driverId) {
                            app(MatchingQueueService::class)->rejoinAfterRide($driverId);
                        }
                    })
                    ->successNotificationTitle('Ride cancelled'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Ride Details')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('status')->badge()
                    ->color(fn($state) => match($state) {
                        'completed' => 'success', 'cancelled' => 'danger',
                        'in_progress' => 'primary', 'accepted' => 'info',
                        default => 'gray',
                    }),
                TextEntry::make('rider.name')->label('Rider'),
                TextEntry::make('driver.name')->label('Driver'),
                TextEntry::make('pickupLocation.name')->label('Pickup'),
                TextEntry::make('destinationLocation.name')->label('Destination'),
                TextEntry::make('estimated_fare_rp')->label('Est. Fare')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                TextEntry::make('actual_fare_rp')->label('Actual Fare')
                    ->formatStateUsing(fn($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                TextEntry::make('driver_accepted_at')->label('Accepted At')->dateTime(),
                TextEntry::make('dropoff_time')->label('Completed At')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRides::route('/'),
            'view'  => Pages\ViewRide::route('/{record}'),
        ];
    }
}
