<?php

namespace App\Filament\Resources;

use App\Events\DriverKycStatusChanged;
use App\Models\AdminAuditLog;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\NotificationService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\FirebaseStorageService;
use App\Filament\Resources\KycResource\Pages;

class KycResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $modelLabel = 'KYC Review';

    protected static ?string $pluralModelLabel = 'KYC';

    protected static ?string $slug = 'kyc';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('driverProfile')
            ->with('driverProfile');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DriverProfile::whereNotNull('email_verified_at')
            ->where('is_verified', false)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
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

                TextColumn::make('driverProfile.is_verified')
                    ->label('KYC Status')
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        $dp = $record->driverProfile;
                        if ($dp?->is_verified) return 'Approved';
                        if ($dp?->email_verified_at && !$dp->is_verified) return 'Pending Review';
                        if ($dp?->student_email) return 'Email Unverified';
                        return 'Not Submitted';
                    })
                    ->color(function ($state, $record) {
                        $dp = $record->driverProfile;
                        if ($dp?->is_verified) return 'success';
                        if ($dp?->email_verified_at && !$dp->is_verified) return 'warning';
                        return 'gray';
                    }),

                TextColumn::make('driverProfile.student_name')
                    ->label('Student Name'),

                TextColumn::make('driverProfile.student_id')
                    ->label('Student ID'),

                TextColumn::make('driverProfile.vehicle_plate')
                    ->label('Plate'),

                TextColumn::make('driverProfile.email_verified_at')
                    ->label('Email Verified')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kyc_status')
                    ->label('KYC Status')
                    ->options([
                        'pending'  => 'Pending Review',
                        'approved' => 'Approved',
                        'other'    => 'Not Ready',
                    ])
                    ->default('pending')
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) return;
                        $query->whereHas('driverProfile', function ($q) use ($data) {
                            if ($data['value'] === 'approved') {
                                $q->where('is_verified', true);
                            } elseif ($data['value'] === 'pending') {
                                $q->whereNotNull('email_verified_at')->where('is_verified', false);
                            } else {
                                $q->where(fn ($q2) =>
                                    $q2->whereNull('email_verified_at')->where('is_verified', false)
                                );
                            }
                        });
                    }),
            ])
            ->actions([
                Action::make('review_kyc')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->visible(fn (User $record) => $record->driverProfile?->email_verified_at || $record->driverProfile?->is_verified)
                    ->modalHeading(fn (User $record) => 'KYC Review — ' . $record->name)
                    ->modalWidth('4xl')
                    ->modalContent(fn (User $record) => new \Illuminate\Support\HtmlString(
                        self::buildReviewModalHtml($record)
                    ))
                    ->form([
                        ToggleButtons::make('decision')
                            ->label('Decision')
                            ->options(['approve' => 'Approve', 'reject' => 'Reject'])
                            ->colors(['approve' => 'success', 'reject' => 'danger'])
                            ->icons([
                                'approve' => 'heroicon-o-check-circle',
                                'reject'  => 'heroicon-o-x-circle',
                            ])
                            ->inline()
                            ->live()
                            ->required(),

                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->rows(3)
                            ->minLength(10)
                            ->required(fn (Get $get) => $get('decision') === 'reject')
                            ->hidden(fn (Get $get) => $get('decision') !== 'reject'),
                    ])
                    ->modalSubmitActionLabel('Submit Decision')
                    ->action(function (User $record, array $data) {
                        if ($data['decision'] === 'approve') {
                            self::approveKyc($record, null);
                        } else {
                            self::rejectKyc($record, $data['reason']);
                        }
                    })
                    ->successNotificationTitle(fn (array $data) => $data['decision'] === 'approve' ? 'KYC approved' : 'KYC rejected'),
            ]);
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

    private static function deleteImage(?string $url): void
    {
        if (! $url) {
            return;
        }

        $storageService = app(FirebaseStorageService::class);
        $objectPath = $storageService->extractObjectPath($url);

        if ($objectPath) {
            $storageService->delete($objectPath);
        } elseif (str_starts_with($url, '/storage/')) {
            Storage::disk('public')->delete(ltrim(str_replace('/storage', '', $url), '/'));
        }
    }

    private static function buildReviewModalHtml(User $record): string
    {
        $dp = $record->driverProfile;

        $ktmSrc = self::resolveImageUrl($dp->ktm_url);
        $ktmHtml = $ktmSrc
            ? '<img src="' . e($ktmSrc) . '" class="max-w-full rounded shadow" alt="KTM Document">'
            : '<div class="flex items-center justify-center h-48 bg-gray-100 dark:bg-gray-800 rounded text-gray-400">No KTM photo</div>';

        $profileSrc = self::resolveImageUrl($record->profile_picture);
        $profileHtml = $profileSrc
            ? '<img src="' . e($profileSrc) . '" class="w-24 h-24 rounded-full object-cover shadow" alt="Profile Photo">'
            : '<div class="flex items-center justify-center w-24 h-24 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 text-xs">No photo</div>';

        $row = fn (string $label, ?string $value) =>
            '<div>'
            . '<span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">' . $label . '</span>'
            . '<p class="mt-1 text-sm text-gray-900 dark:text-white">' . e($value ?? '—') . '</p>'
            . '</div>';

        return '
        <div class="grid grid-cols-2 gap-6 p-2 pb-4">
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-4">' . $profileHtml . '</div>
                ' . $ktmHtml . '
            </div>
            <div class="space-y-4">
                ' . $row('Student Name', $dp->student_name)
                 . $row('Student ID', $dp->student_id)
                 . $row('Student Email', $dp->student_email)
                 . $row('WhatsApp', $record->phone_number)
                 . $row('Vehicle Plate', $dp->vehicle_plate)
                 . $row('Vehicle Color', $dp->vehicle_color) . '
            </div>
        </div>';
    }

    private static function approveKyc(User $record, ?string $reason): void
    {
        $ktmUrl = $record->driverProfile->ktm_url;

        DB::transaction(function () use ($record, $reason) {
            $record->driverProfile->update([
                'is_verified' => true,
                'ktm_url'     => null,
            ]);
            AdminAuditLog::create([
                'admin_id'    => auth()->id(),
                'action_type' => 'kyc_approve',
                'target_type' => DriverProfile::class,
                'target_id'   => $record->driverProfile->id,
                'changes'     => ['is_verified' => true],
                'reason'      => $reason,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        });

        self::deleteImage($ktmUrl);

        try {
            app(NotificationService::class)->sendKycApprovedToDriver($record);
        } catch (\Exception $e) {
            \Log::warning('Failed to send KYC approved notification', [
                'driver_id' => $record->id,
                'error'     => $e->getMessage(),
            ]);
        }

        broadcast(new DriverKycStatusChanged($record->fresh(['driverProfile']), true));
    }

    private static function rejectKyc(User $record, string $reason): void
    {
        $ktmUrl = $record->driverProfile->ktm_url;
        $profilePicture = $record->profile_picture;

        DB::transaction(function () use ($record, $reason) {
            $record->driverProfile->update([
                'is_verified'       => false,
                'email_verified_at' => null,
                'student_email'     => null,
                'student_id'        => null,
                'student_name'      => null,
                'vehicle_plate'     => null,
                'vehicle_color'     => null,
                'ktm_url'           => null,
            ]);
            $record->update([
                'phone_number'    => null,
                'profile_picture' => null,
            ]);
            AdminAuditLog::create([
                'admin_id'    => auth()->id(),
                'action_type' => 'kyc_reject',
                'target_type' => DriverProfile::class,
                'target_id'   => $record->driverProfile->id,
                'changes'     => ['is_verified' => false, 'email_verified_at' => null],
                'reason'      => $reason,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        });

        self::deleteImage($ktmUrl);
        self::deleteImage($profilePicture);

        try {
            app(NotificationService::class)->sendKycRejectedToDriver($record, $reason);
        } catch (\Exception $e) {
            \Log::warning('Failed to send KYC rejected notification', [
                'driver_id' => $record->id,
                'error'     => $e->getMessage(),
            ]);
        }

        broadcast(new DriverKycStatusChanged($record->fresh(['driverProfile']), false, $reason));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKyc::route('/'),
        ];
    }
}
