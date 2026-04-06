<?php
namespace App\Filament\Resources;

use App\Events\UserAccountStatusChanged;
use App\Models\AdminAuditLog;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\RiderResource\Pages;

class RiderResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'Rider';
    protected static ?string $pluralModelLabel = 'Riders';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('role', ['rider', 'both']);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile_picture')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl('https://ui-avatars.com/api/?background=e2e8f0&color=94a3b8&name=U')
                    ->getStateUsing(fn (User $record) => self::resolveImageUrl($record->profile_picture))
                    ->size(32),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone_number')->label('Phone'),
                TextColumn::make('role')->badge()
                    ->color(fn(string $state): string => match($state) {
                        'both' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('is_active')->label('Status')->badge()
                    ->formatStateUsing(fn(bool $state): string => $state ? 'Active' : 'Suspended')
                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->actions([
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn(User $record): bool => $record->is_active)
                    ->form([TextInput::make('reason')->label('Reason (optional)')])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->update(['is_active' => false]);
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'rider_suspend',
                                'target_type' => User::class,
                                'target_id'   => $record->id,
                                'changes'     => ['is_active' => false],
                                'reason'      => $data['reason'] ?? null,
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                        broadcast(new UserAccountStatusChanged($record->fresh(['driverProfile']), true, $data['reason'] ?? null));
                    })
                    ->successNotificationTitle('Rider suspended'),

                Action::make('unsuspend')
                    ->label('Unsuspend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn(User $record): bool => !$record->is_active)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->update(['is_active' => true]);
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'rider_unsuspend',
                                'target_type' => User::class,
                                'target_id'   => $record->id,
                                'changes'     => ['is_active' => true],
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                        broadcast(new UserAccountStatusChanged($record->fresh(['driverProfile']), false));
                    })
                    ->successNotificationTitle('Rider unsuspended'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form;
    }

    private static function resolveImageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return url($url);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Profile')->schema([
                ImageEntry::make('profile_picture')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl('https://ui-avatars.com/api/?background=e2e8f0&color=94a3b8&name=U')
                    ->getStateUsing(fn (User $record) => self::resolveImageUrl($record->profile_picture))
                    ->size(80),
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('phone_number')->label('Phone'),
                TextEntry::make('role')->badge(),
                TextEntry::make('is_active')->label('Status')->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Active' : 'Suspended')
                    ->color(fn($state) => $state ? 'success' : 'danger'),
                TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRiders::route('/'),
            'view'  => Pages\ViewRider::route('/{record}'),
        ];
    }
}
