<?php

namespace App\Filament\Resources;

use App\Models\DriverProfile;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\DriverProfileResource\Pages;

class DriverProfileResource extends Resource
{
    protected static ?string $model = DriverProfile::class;
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $modelLabel = 'Driver Profile';
    protected static ?string $pluralModelLabel = 'Driver Profiles';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Driver')->searchable()->sortable(),
                TextColumn::make('online_status')->label('Status')
                    ->state(fn (DriverProfile $record): string => $record->went_online_at ? 'Online' : 'Offline')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Online' ? 'success' : 'gray'),
                TextColumn::make('queue_status')->label('Queue')
                    ->state(fn (DriverProfile $record): string => $record->queue_joined_at
                        ? 'In Queue'
                        : ($record->isInCooldown() ? 'Cooldown' : '-')
                    )
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'In Queue' => 'info',
                        'Cooldown' => 'warning',
                        default    => 'gray',
                    }),
                TextColumn::make('is_verified')->label('KYC')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Verified' : 'Unverified')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('credits_balance')->label('Credits')->sortable(),
                TextColumn::make('rating_average')->label('Rating')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . ' ★')
                    ->sortable(),
                TextColumn::make('vehicle_plate')->label('Plate')->placeholder('-'),
                TextColumn::make('vehicle_type')->label('Vehicle'),
            ])
            ->filters([
                TernaryFilter::make('is_verified')
                    ->label('KYC Status')
                    ->trueLabel('Verified')
                    ->falseLabel('Unverified'),
                TernaryFilter::make('online')
                    ->label('Online Status')
                    ->trueLabel('Online')
                    ->falseLabel('Offline')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('went_online_at'),
                        false: fn (Builder $query) => $query->whereNull('went_online_at'),
                    ),
                TernaryFilter::make('in_queue')
                    ->label('Queue Status')
                    ->trueLabel('In Queue')
                    ->falseLabel('Not in Queue')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('queue_joined_at'),
                        false: fn (Builder $query) => $query->whereNull('queue_joined_at'),
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Driver Info')->schema([
                TextEntry::make('user.name')->label('Name'),
                TextEntry::make('user.email')->label('Email'),
                TextEntry::make('user.phone_number')->label('Phone')->placeholder('-'),
                TextEntry::make('is_verified')->label('KYC')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Verified' : 'Unverified')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextEntry::make('went_online_at')->label('Online Since')->dateTime()->placeholder('Offline'),
                TextEntry::make('queue_joined_at')->label('In Queue Since')->dateTime()->placeholder('Not in queue'),
            ])->columns(2),

            Section::make('Vehicle')->schema([
                TextEntry::make('vehicle_type'),
                TextEntry::make('vehicle_make')->placeholder('-'),
                TextEntry::make('vehicle_model')->placeholder('-'),
                TextEntry::make('vehicle_year')->placeholder('-'),
                TextEntry::make('vehicle_color')->placeholder('-'),
                TextEntry::make('vehicle_plate')->placeholder('-'),
            ])->columns(2),

            Section::make('Credits')->schema([
                TextEntry::make('credits_balance')->label('Balance'),
                TextEntry::make('credits_total_earned')->label('Total Earned'),
                TextEntry::make('credits_total_spent')->label('Total Spent'),
            ])->columns(3),

            Section::make('Rating')->schema([
                TextEntry::make('rating_average')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . ' ★'),
                TextEntry::make('rating_count')->label('Total Ratings'),
                TextEntry::make('total_rides_given')->label('Total Rides'),
            ])->columns(3),

            Section::make('Queue Status')->schema([
                TextEntry::make('queue_joined_at')->dateTime()->placeholder('-'),
                TextEntry::make('queue_cooldown_until')->label('Cooldown Until')->dateTime()->placeholder('No cooldown'),
                TextEntry::make('decline_count')->label('Declines'),
                TextEntry::make('decline_window_start')->dateTime()->placeholder('-'),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverProfiles::route('/'),
            'view'  => Pages\ViewDriverProfile::route('/{record}'),
        ];
    }
}
