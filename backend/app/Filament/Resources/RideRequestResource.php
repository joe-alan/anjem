<?php

namespace App\Filament\Resources;

use App\Models\RideRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\RideRequestResource\Pages;

class RideRequestResource extends Resource
{
    protected static ?string $model = RideRequest::class;
    protected static ?string $navigationGroup = 'Rides';
    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';
    protected static ?string $modelLabel = 'Ride Request';
    protected static ?string $pluralModelLabel = 'Ride Requests';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['rider', 'currentDriver', 'pickupLocation', 'destinationLocation']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('rider.name')->label('Rider')->searchable()
                    ->placeholder('<deleted user>'),
                TextColumn::make('route')->label('Route')
                    ->state(fn (RideRequest $record): string =>
                        ($record->pickupLocation?->name ?? '?') . ' → ' . ($record->destinationLocation?->name ?? '?')
                    ),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'     => 'warning',
                        'matched'     => 'info',
                        'in_progress' => 'primary',
                        'completed'   => 'success',
                        'expired'     => 'gray',
                        'cancelled'   => 'danger',
                        default       => 'gray',
                    }),
                TextColumn::make('currentDriver.name')->label('Current Driver')->placeholder('<deleted user>'),
                TextColumn::make('estimated_fare_rp')->label('Est. Fare')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                TextColumn::make('passenger_count')->label('Pax'),
                TextColumn::make('expires_at')->label('Expires')->since()->sortable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'     => 'Pending',
                        'matched'     => 'Matched',
                        'in_progress' => 'In Progress',
                        'completed'   => 'Completed',
                        'expired'     => 'Expired',
                        'cancelled'   => 'Cancelled',
                    ])
                    ->multiple(),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Request Details')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('rider.name')->label('Rider')->placeholder('<deleted user>'),
                TextEntry::make('pickupLocation.name')->label('Pickup'),
                TextEntry::make('destinationLocation.name')->label('Destination'),
                TextEntry::make('status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success', 'cancelled' => 'danger', 'expired' => 'gray',
                        'pending' => 'warning', 'matched' => 'info', 'in_progress' => 'primary',
                        default => 'gray',
                    }),
                TextEntry::make('estimated_fare_rp')->label('Est. Fare')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),
                TextEntry::make('passenger_count')->label('Passengers'),
                TextEntry::make('estimated_distance_km')->label('Est. Distance')->suffix(' km')->placeholder('-'),
                TextEntry::make('estimated_duration_minutes')->label('Est. Duration')->suffix(' min')->placeholder('-'),
            ])->columns(2),

            Section::make('Dispatch Info')->schema([
                TextEntry::make('currentDriver.name')->label('Current Driver')->placeholder('<deleted user>'),
                TextEntry::make('matched_at')->dateTime()->placeholder('-'),
                TextEntry::make('expires_at')->dateTime()->placeholder('-'),
                TextEntry::make('expiry_generation')->label('Expiry Gen'),
            ])->columns(2),

            Section::make('Related Ride')->schema([
                TextEntry::make('ride.id')->label('Ride ID')
                    ->url(fn (RideRequest $record) => $record->ride
                        ? RideResource::getUrl('view', ['record' => $record->ride->id])
                        : null
                    )->placeholder('No ride created'),
                TextEntry::make('ride.status')->label('Ride Status')->badge()->placeholder('-'),
            ])->columns(2),

            Section::make('Timestamps')->schema([
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('updated_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRideRequests::route('/'),
            'view'  => Pages\ViewRideRequest::route('/{record}'),
        ];
    }
}
