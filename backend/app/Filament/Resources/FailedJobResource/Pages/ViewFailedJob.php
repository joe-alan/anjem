<?php

namespace App\Filament\Resources\FailedJobResource\Pages;

use App\Filament\Resources\FailedJobResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewFailedJob extends ViewRecord
{
    protected static string $resource = FailedJobResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('exception')
                    ->label('Exception')
                    ->fontFamily('mono')
                    ->copyable()
                    ->columnSpanFull(),
                TextEntry::make('payload')
                    ->label('Payload')
                    ->formatStateUsing(fn (string $state) => json_encode(json_decode($state), JSON_PRETTY_PRINT))
                    ->fontFamily('mono')
                    ->columnSpanFull(),
            ]);
    }
}
