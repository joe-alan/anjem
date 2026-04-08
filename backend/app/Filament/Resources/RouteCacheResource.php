<?php

namespace App\Filament\Resources;

use App\Models\RouteCache;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\RouteCacheResource\Pages;

class RouteCacheResource extends Resource
{
    protected static ?string $model = RouteCache::class;
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $modelLabel = 'Route Cache';
    protected static ?string $pluralModelLabel = 'Route Caches';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['originLocation', 'destinationLocation']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('route')->label('Route')
                    ->state(fn (RouteCache $record): string =>
                        ($record->originLocation?->name ?? '?') . ' → ' . ($record->destinationLocation?->name ?? '?')
                    )->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('originLocation', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                            ->orWhereHas('destinationLocation', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
                    }),
                TextColumn::make('profile')->badge(),
                TextColumn::make('distance_meters')->label('Distance')
                    ->formatStateUsing(fn (int $state): string => round($state / 1000, 1) . ' km')
                    ->sortable(),
                TextColumn::make('duration_minutes')->label('Duration')->suffix(' min')->sortable(),
                TextColumn::make('fetch_count')->label('Uses')->sortable(),
                TextColumn::make('last_fetched_at')->label('Last Used')->since()->sortable(),
                TextColumn::make('freshness')->label('Status')
                    ->state(fn (RouteCache $record): string => $record->isFresh() ? 'Fresh' : 'Stale')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Fresh' ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('profile')
                    ->options([
                        'driving' => 'Driving',
                        'walking' => 'Walking',
                        'cycling' => 'Cycling',
                    ]),
                TernaryFilter::make('freshness')
                    ->label('Freshness')
                    ->trueLabel('Fresh')
                    ->falseLabel('Stale')
                    ->queries(
                        true: fn (Builder $query) => $query->where('last_fetched_at', '>=', now()->subDays(7)),
                        false: fn (Builder $query) => $query->where('last_fetched_at', '<', now()->subDays(7)),
                    ),
            ])
            ->defaultSort('last_fetched_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Route')->schema([
                TextEntry::make('origin')->label('Origin')
                    ->state(fn (RouteCache $record): string => $record->originLocation?->name ?? '-'),
                TextEntry::make('destination')->label('Destination')
                    ->state(fn (RouteCache $record): string => $record->destinationLocation?->name ?? '-'),
                TextEntry::make('profile')->badge(),
                TextEntry::make('distance_meters')->label('Distance')
                    ->formatStateUsing(fn (int $state): string => round($state / 1000, 1) . ' km'),
                TextEntry::make('duration_minutes')->label('Duration')->suffix(' min'),
            ])->columns(2),

            Section::make('Cache Stats')->schema([
                TextEntry::make('fetch_count')->label('Times Used'),
                TextEntry::make('last_fetched_at')->label('Last Used')->dateTime(),
                TextEntry::make('freshness')->label('Status')
                    ->state(fn (RouteCache $record): string => $record->isFresh() ? 'Fresh' : 'Stale')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Fresh' ? 'success' : 'danger'),
                TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRouteCaches::route('/'),
            'view'  => Pages\ViewRouteCache::route('/{record}'),
        ];
    }
}
