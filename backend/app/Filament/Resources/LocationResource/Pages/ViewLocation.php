<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLocation extends ViewRecord
{
    protected static string $resource = LocationResource::class;
    protected static string $view = 'filament.resources.location-resource.pages.view-location';

    public function getMapData(): array
    {
        $record = $this->record;

        if (! $record->coordinates) {
            return ['hasCoordinates' => false];
        }

        return [
            'hasCoordinates' => true,
            'lat' => $record->coordinates->latitude,
            'lng' => $record->coordinates->longitude,
            'name' => $record->name,
            'is_beacon' => $record->is_beacon,
            'radius_m' => $record->radius_m,
            'mapbox_token' => config('services.mapbox.public_token'),
        ];
    }
}
