<?php
namespace App\Filament\Resources;

use App\Models\AdminAuditLog;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\AuditLogResource\Pages;

class AuditLogResource extends Resource
{
    protected static ?string $model = AdminAuditLog::class;
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $modelLabel = 'Audit Log';
    protected static ?string $pluralModelLabel = 'Audit Logs';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('admin.name')->label('Admin')->searchable(),
                TextColumn::make('action_type')->label('Action')->badge()
                    ->color(fn(string $state): string => match(true) {
                        in_array($state, ['kyc_approve', 'driver_unsuspend', 'rider_unsuspend']) => 'success',
                        in_array($state, ['kyc_reject']) => 'danger',
                        in_array($state, ['driver_suspend', 'rider_suspend', 'credit_deduct']) => 'warning',
                        in_array($state, ['credit_grant']) => 'info',
                        in_array($state, ['force_ride_status', 'ride_cancel', 'ride_force_complete', 'request_cancel']) => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('target_type')->label('Target')
                    ->formatStateUsing(fn($state) => class_basename($state)),
                TextColumn::make('target_id')->label('Target ID'),
                TextColumn::make('reason')->limit(50)->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->placeholder('—'),
                TextColumn::make('created_at')->label('When')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('action_type')
                    ->label('Action Type')
                    ->options([
                        'kyc_approve'         => 'KYC Approve',
                        'kyc_reject'          => 'KYC Reject',
                        'driver_suspend'      => 'Driver Suspend',
                        'driver_unsuspend'    => 'Driver Unsuspend',
                        'rider_suspend'       => 'Rider Suspend',
                        'rider_unsuspend'     => 'Rider Unsuspend',
                        'credit_grant'        => 'Credit Grant',
                        'credit_deduct'       => 'Credit Deduct',
                        'ride_cancel'         => 'Ride Cancel',
                        'ride_force_complete' => 'Ride Force Complete',
                        'force_ride_status'   => 'Force Ride Status',
                        'request_cancel'      => 'Request Cancel',
                    ]),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Audit Entry')->schema([
                TextEntry::make('admin.name')->label('Admin'),
                TextEntry::make('action_type')->label('Action')->badge(),
                TextEntry::make('target_type')->label('Target Type')
                    ->formatStateUsing(fn($state) => class_basename($state)),
                TextEntry::make('target_id')->label('Target ID'),
                TextEntry::make('reason')->placeholder('—')->columnSpanFull(),
                TextEntry::make('ip_address')->label('IP Address'),
                TextEntry::make('user_agent')->label('User Agent')->columnSpanFull(),
                TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
            Section::make('Changes')->schema([
                TextEntry::make('changes')
                    ->label('Changes (JSON)')
                    ->state(fn(AdminAuditLog $record): string => json_encode($record->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view'  => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
