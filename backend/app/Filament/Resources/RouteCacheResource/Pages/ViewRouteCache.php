<?php

namespace App\Filament\Resources\RouteCacheResource\Pages;

use App\Filament\Resources\RouteCacheResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRouteCache extends ViewRecord
{
    protected static string $resource = RouteCacheResource::class;
    protected static string $view = 'filament.resources.route-cache-resource.pages.view-route-cache';

    public function getMapData(): array
    {
        $record = $this->record;
        $record->loadMissing(['originLocation', 'destinationLocation']);

        $origin = $record->originLocation;
        $destination = $record->destinationLocation;

        if (! $origin?->coordinates || ! $destination?->coordinates) {
            return ['hasCoordinates' => false];
        }

        return [
            'hasCoordinates' => true,
            'origin' => [
                'lat' => $origin->coordinates->latitude,
                'lng' => $origin->coordinates->longitude,
                'name' => $origin->name,
            ],
            'destination' => [
                'lat' => $destination->coordinates->latitude,
                'lng' => $destination->coordinates->longitude,
                'name' => $destination->name,
            ],
            'route_geometry' => $record->route_geometry,
            'mapbox_token' => config('services.mapbox.public_token'),
        ];
    }
}
