<x-filament-panels::page>
    @php $mapData = $this->getMapData(); @endphp

    @if($mapData['hasCoordinates'])
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">Route Map</x-slot>
            <div id="route-map" style="width:100%; height:400px; border-radius:0.5rem;"></div>
        </x-filament::section>
    </div>
    @endif

    {{ $this->infolist }}

    @if($mapData['hasCoordinates'])

    <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const data = @json($mapData);
        mapboxgl.accessToken = data.mapbox_token;

        const bounds = new mapboxgl.LngLatBounds();
        bounds.extend([data.origin.lng, data.origin.lat]);
        bounds.extend([data.destination.lng, data.destination.lat]);

        const map = new mapboxgl.Map({
            container: 'route-map',
            style: 'mapbox://styles/mapbox/streets-v12',
            bounds: bounds,
            fitBoundsOptions: { padding: 60 },
        });

        map.addControl(new mapboxgl.NavigationControl(), 'top-left');

        // Origin marker (green)
        const originEl = document.createElement('div');
        originEl.style.cssText = 'width:14px;height:14px;border-radius:50%;background:#22c55e;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);';
        new mapboxgl.Marker({ element: originEl })
            .setLngLat([data.origin.lng, data.origin.lat])
            .setPopup(new mapboxgl.Popup({ offset: 12 }).setHTML('<strong>' + data.origin.name + '</strong><br><span style="font-size:12px;color:#666;">Origin</span>'))
            .addTo(map);

        // Destination marker (red)
        const destEl = document.createElement('div');
        destEl.style.cssText = 'width:14px;height:14px;border-radius:50%;background:#ef4444;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);';
        new mapboxgl.Marker({ element: destEl })
            .setLngLat([data.destination.lng, data.destination.lat])
            .setPopup(new mapboxgl.Popup({ offset: 12 }).setHTML('<strong>' + data.destination.name + '</strong><br><span style="font-size:12px;color:#666;">Destination</span>'))
            .addTo(map);

        // Route polyline from cached geometry (no API call)
        if (data.route_geometry) {
            map.on('load', function() {
                let geojson = data.route_geometry;
                if (typeof geojson === 'string') {
                    try { geojson = JSON.parse(geojson); } catch(e) { return; }
                }

                const feature = geojson.type === 'Feature' ? geojson : {
                    type: 'Feature',
                    geometry: geojson,
                    properties: {},
                };

                map.addSource('route', { type: 'geojson', data: feature });
                map.addLayer({
                    id: 'route-line',
                    type: 'line',
                    source: 'route',
                    layout: { 'line-join': 'round', 'line-cap': 'round' },
                    paint: { 'line-color': '#6366f1', 'line-width': 4, 'line-opacity': 0.8 },
                });

                // Fit bounds to route
                if (feature.geometry && feature.geometry.coordinates) {
                    const routeBounds = new mapboxgl.LngLatBounds();
                    feature.geometry.coordinates.forEach(c => routeBounds.extend(c));
                    map.fitBounds(routeBounds, { padding: 60 });
                }
            });
        }
    });
    </script>
    @endif
</x-filament-panels::page>
