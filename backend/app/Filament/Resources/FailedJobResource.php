<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FailedJobResource\Pages;
use App\Models\FailedJob;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class FailedJobResource extends Resource
{
    protected static ?string $model = FailedJob::class;

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Failed Jobs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label('ID'),
                TextColumn::make('uuid')
                    ->copyable()
                    ->limit(20),
                TextColumn::make('queue')
                    ->badge(),
                TextColumn::make('job_name')
                    ->label('Job')
                    ->getStateUsing(fn (FailedJob $record) => $record->job_name),
                TextColumn::make('exception')
                    ->label('Exception')
                    ->limit(100),
                TextColumn::make('failed_at')
                    ->sortable()
                    ->dateTime()
                    ->label('Failed At'),
            ])
            ->defaultSort('failed_at', 'desc')
            ->filters([
                SelectFilter::make('queue')
                    ->options(fn () => FailedJob::query()->distinct()->pluck('queue', 'queue')->toArray()),
                Filter::make('failed_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('failed_from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('failed_until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['failed_from'], fn ($q, $v) => $q->whereDate('failed_at', '>=', $v))
                            ->when($data['failed_until'], fn ($q, $v) => $q->whereDate('failed_at', '<=', $v));
                    }),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (FailedJob $record) {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        $record->delete();
                        Notification::make()
                            ->title('Job queued for retry')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->using(function (FailedJob $record) {
                        Artisan::call('queue:forget', ['id' => [$record->uuid]]);
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('retry_selected')
                    ->label('Retry Selected')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (Collection $records) {
                        $records->each(function (FailedJob $record) {
                            Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        });
                        $records->toQuery()->delete();
                        Notification::make()
                            ->title('Selected jobs queued for retry')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->using(function (Collection $records) {
                            $records->each(function (FailedJob $record) {
                                Artisan::call('queue:forget', ['id' => [$record->uuid]]);
                            });
                            $records->toQuery()->delete();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFailedJobs::route('/'),
            'view' => Pages\ViewFailedJob::route('/{record}'),
        ];
    }
}
