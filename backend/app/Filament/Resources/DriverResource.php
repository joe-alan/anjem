<?php

namespace App\Filament\Resources;

use App\Models\AdminAuditLog;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\CreditService;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\DriverResource\Pages;

class DriverResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $modelLabel = 'Driver';

    protected static ?string $pluralModelLabel = 'Drivers';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('driverProfile')->with('driverProfile');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('phone_number')
                    ->label('Phone'),

                TextColumn::make('driverProfile.is_verified')
                    ->label('KYC')
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        $dp = $record->driverProfile;
                        if ($dp && $dp->is_verified && $dp->email_verified_at) {
                            return 'Approved';
                        }
                        if ($dp && $dp->email_verified_at && !$dp->is_verified) {
                            return 'Email Verified';
                        }
                        return 'Pending';
                    })
                    ->color(function ($state, $record) {
                        $dp = $record->driverProfile;
                        if ($dp && $dp->is_verified && $dp->email_verified_at) {
                            return 'success';
                        }
                        if ($dp && $dp->email_verified_at && !$dp->is_verified) {
                            return 'warning';
                        }
                        return 'danger';
                    }),

                TextColumn::make('driverProfile.credits_balance')
                    ->label('Credits')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('driverProfile.rating_average')
                    ->label('Rating')
                    ->numeric(decimalPlaces: 1)
                    ->suffix('★'),

                TextColumn::make('driverProfile.went_online_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Online' : 'Offline')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kyc_status')
                    ->options([
                        'approved'       => 'Approved',
                        'email_verified' => 'Email Verified',
                        'pending'        => 'Pending',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) {
                            return;
                        }
                        $query->whereHas('driverProfile', function ($q) use ($data) {
                            if ($data['value'] === 'approved') {
                                $q->where('is_verified', true)->whereNotNull('email_verified_at');
                            } elseif ($data['value'] === 'email_verified') {
                                $q->whereNotNull('email_verified_at')->where('is_verified', false);
                            } else {
                                $q->where('is_verified', false)->whereNull('email_verified_at');
                            }
                        });
                    }),

                TernaryFilter::make('online')
                    ->label('Online Status')
                    ->trueLabel('Online')
                    ->falseLabel('Offline')
                    ->queries(
                        true: fn ($q) => $q->whereHas('driverProfile', fn ($q2) => $q2->whereNotNull('went_online_at')),
                        false: fn ($q) => $q->whereHas('driverProfile', fn ($q2) => $q2->whereNull('went_online_at')),
                    ),
            ])
            ->actions([
                Action::make('approve_kyc')
                    ->label('Approve KYC')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record) => !($record->driverProfile && $record->driverProfile->is_verified && $record->driverProfile->email_verified_at))
                    ->form([
                        TextInput::make('reason')->label('Reason (optional)'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $record->driverProfile->update(['is_verified' => true]);
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'kyc_approve',
                                'target_type' => DriverProfile::class,
                                'target_id'   => $record->driverProfile->id,
                                'changes'     => ['is_verified' => true],
                                'reason'      => $data['reason'] ?? null,
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                    })
                    ->successNotificationTitle('KYC approved'),

                Action::make('reject_kyc')
                    ->label('Reject KYC')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (User $record) => $record->driverProfile?->is_verified || $record->driverProfile?->email_verified_at)
                    ->form([
                        TextInput::make('reason')->label('Reason')->required()->minLength(10),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $record->driverProfile->update(['is_verified' => false, 'email_verified_at' => null]);
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'kyc_reject',
                                'target_type' => DriverProfile::class,
                                'target_id'   => $record->driverProfile->id,
                                'changes'     => ['is_verified' => false, 'email_verified_at' => null],
                                'reason'      => $data['reason'],
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                    })
                    ->successNotificationTitle('KYC rejected'),

                Action::make('grant_credits')
                    ->label('Grant Credits')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('reason')->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            app(CreditService::class)->addCredits($record->id, (int) $data['amount'], $data['reason']);
                            $record->driverProfile->refresh();
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'credit_grant',
                                'target_type' => DriverProfile::class,
                                'target_id'   => $record->driverProfile->id,
                                'changes'     => [
                                    'amount'      => (int) $data['amount'],
                                    'new_balance' => $record->driverProfile->credits_balance,
                                ],
                                'reason'      => $data['reason'],
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                    })
                    ->successNotificationTitle('Credits granted'),

                Action::make('deduct_credits')
                    ->label('Deduct Credits')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->form([
                        TextInput::make('amount')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('reason')->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data) {
                        try {
                            $result = app(CreditService::class)->adminDeductCredits(
                                $record->driverProfile,
                                (int) $data['amount'],
                                $data['reason']
                            );
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'credit_deduct',
                                'target_type' => DriverProfile::class,
                                'target_id'   => $record->driverProfile->id,
                                'changes'     => [
                                    'amount'         => (int) $data['amount'],
                                    'balance_before' => $result['balance_before'],
                                    'balance_after'  => $result['balance_after'],
                                ],
                                'reason'      => $data['reason'],
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Insufficient credits')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->successNotificationTitle('Credits deducted'),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record) => $record->is_active)
                    ->form([
                        TextInput::make('reason')->label('Reason (optional)'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $record->update(['is_active' => false]);
                            if ($record->driverProfile) {
                                $record->driverProfile->update(['went_online_at' => null]);
                            }
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'driver_suspend',
                                'target_type' => User::class,
                                'target_id'   => $record->id,
                                'changes'     => ['is_active' => false],
                                'reason'      => $data['reason'] ?? null,
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                    })
                    ->successNotificationTitle('Driver suspended'),

                Action::make('unsuspend')
                    ->label('Unsuspend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (User $record) => !$record->is_active)
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        DB::transaction(function () use ($record) {
                            $record->update(['is_active' => true]);
                            AdminAuditLog::create([
                                'admin_id'    => auth()->id(),
                                'action_type' => 'driver_unsuspend',
                                'target_type' => User::class,
                                'target_id'   => $record->id,
                                'changes'     => ['is_active' => true],
                                'ip_address'  => request()->ip(),
                                'user_agent'  => request()->userAgent(),
                            ]);
                        });
                    })
                    ->successNotificationTitle('Driver unsuspended'),

                Action::make('view_document')
                    ->label('View Document')
                    ->icon('heroicon-o-document')
                    ->visible(fn (User $record) => !empty($record->driverProfile?->ktm_url))
                    ->modalContent(fn (User $record) => new \Illuminate\Support\HtmlString(
                        '<div class="text-center p-4"><img src="' . e(asset('storage/' . $record->driverProfile->ktm_url)) . '" class="max-w-full mx-auto rounded shadow" alt="KTM Document"></div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Personal Info')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email'),
                    TextEntry::make('phone_number')->label('Phone'),
                    TextEntry::make('role'),
                    TextEntry::make('is_active')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Suspended')
                        ->color(fn ($state) => $state ? 'success' : 'danger'),
                ])
                ->columns(2),

            Section::make('KYC & Verification')
                ->schema([
                    TextEntry::make('driverProfile.is_verified')
                        ->label('Admin Verified')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                        ->color(fn ($state) => $state ? 'success' : 'danger'),
                    TextEntry::make('driverProfile.email_verified_at')
                        ->label('Email Verified At')
                        ->dateTime(),
                    TextEntry::make('driverProfile.student_email')
                        ->label('Student Email'),
                    TextEntry::make('driverProfile.ktm_url')
                        ->label('KTM Path'),
                ])
                ->columns(2),

            Section::make('Vehicle')
                ->schema([
                    TextEntry::make('driverProfile.vehicle_type')->label('Type'),
                    TextEntry::make('driverProfile.vehicle_plate')->label('Plate'),
                    TextEntry::make('driverProfile.vehicle_color')->label('Color'),
                ])
                ->columns(3),

            Section::make('Credits')
                ->schema([
                    TextEntry::make('driverProfile.credits_balance')
                        ->label('Balance')
                        ->numeric(),
                    TextEntry::make('driverProfile.credits_total_earned')
                        ->label('Total Earned')
                        ->numeric(),
                    TextEntry::make('driverProfile.credits_total_spent')
                        ->label('Total Spent')
                        ->numeric(),
                ])
                ->columns(3),

            Section::make('Rating')
                ->schema([
                    TextEntry::make('driverProfile.rating_average')
                        ->label('Average')
                        ->numeric(decimalPlaces: 2)
                        ->suffix(' ★'),
                    TextEntry::make('driverProfile.rating_count')
                        ->label('Count')
                        ->numeric(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'view'  => Pages\ViewDriver::route('/{record}'),
        ];
    }
}
