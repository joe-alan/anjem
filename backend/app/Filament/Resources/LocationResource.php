<?php

namespace App\Filament\Resources;

use App\Models\Location;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\LocationResource\Pages;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('coordinates')
                    ->label('Coordinates')
                    ->state(fn (Location $record): string =>
                        $record->coordinates
                            ? round($record->coordinates->latitude, 6) . ', ' . round($record->coordinates->longitude, 6)
                            : '-'
                    ),
                TextColumn::make('is_beacon')->label('Type')->badge()
                    ->state(fn (Location $record): string => $record->is_beacon ? 'Beacon' : 'Destination')
                    ->color(fn (string $state): string => $state === 'Beacon' ? 'success' : 'gray'),
                TextColumn::make('usage_count')->sortable(),
                TextColumn::make('is_active')->label('Status')->badge()
                    ->state(fn (Location $record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
                TextColumn::make('radius_m')->label('Radius')->suffix(' m'),
                TextColumn::make('beacon_capacity')->label('Capacity')->placeholder('-'),
                TextColumn::make('current_queue_size')->label('Queue')->placeholder('-'),
            ])
            ->filters([
                TernaryFilter::make('is_beacon')
                    ->label('Type')
                    ->trueLabel('Beacons')
                    ->falseLabel('Destinations'),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->defaultSort('usage_count', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Location Info')->schema([
                TextEntry::make('name'),
                TextEntry::make('address')->placeholder('-'),
                TextEntry::make('coordinates')
                    ->state(fn (Location $record): string =>
                        $record->coordinates
                            ? round($record->coordinates->latitude, 6) . ', ' . round($record->coordinates->longitude, 6)
                            : '-'
                    ),
                TextEntry::make('is_beacon')->label('Type')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Beacon' : 'Destination')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextEntry::make('is_active')->label('Status')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextEntry::make('radius_m')->suffix(' m'),
                TextEntry::make('usage_count'),
            ])->columns(2),

            Section::make('Beacon Details')
                ->visible(fn (Location $record): bool => $record->is_beacon)
                ->schema([
                    TextEntry::make('beacon_capacity')->label('Max Capacity'),
                    TextEntry::make('current_queue_size')->label('Current Queue Size'),
                ])->columns(2),

            Section::make('Usage Stats')->schema([
                TextEntry::make('pickup_rides_count')
                    ->label('Rides as Pickup')
                    ->state(fn (Location $record): int => $record->pickupRides()->count()),
                TextEntry::make('dropoff_rides_count')
                    ->label('Rides as Destination')
                    ->state(fn (Location $record): int => $record->dropoffRides()->count()),
                TextEntry::make('origin_requests_count')
                    ->label('Requests as Origin')
                    ->state(fn (Location $record): int => $record->originRideRequests()->count()),
                TextEntry::make('destination_requests_count')
                    ->label('Requests as Destination')
                    ->state(fn (Location $record): int => $record->destinationRideRequests()->count()),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'view'  => Pages\ViewLocation::route('/{record}'),
        ];
    }
}
