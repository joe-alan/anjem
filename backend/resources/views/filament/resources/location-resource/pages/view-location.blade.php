<x-filament-panels::page>
    @php $mapData = $this->getMapData(); @endphp

    @if($mapData['hasCoordinates'])
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">Map</x-slot>
            <div id="location-map" style="width:100%; height:400px; border-radius:0.5rem;"></div>
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

        const map = new mapboxgl.Map({
            container: 'location-map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [data.lng, data.lat],
            zoom: 15,
        });

        map.addControl(new mapboxgl.NavigationControl(), 'top-left');

        // Pin marker
        const el = document.createElement('div');
        el.style.width = '16px';
        el.style.height = '16px';
        el.style.borderRadius = '50%';
        el.style.backgroundColor = data.is_beacon ? '#22c55e' : '#6366f1';
        el.style.border = '3px solid white';
        el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.3)';

        new mapboxgl.Marker({ element: el })
            .setLngLat([data.lng, data.lat])
            .setPopup(new mapboxgl.Popup({ offset: 12 }).setHTML(
                '<strong>' + data.name + '</strong><br>' +
                '<span style="font-size:12px;color:#666;">' + (data.is_beacon ? 'Beacon' : 'Destination') + '</span>'
            ))
            .addTo(map);

        // Radius circle for beacons
        if (data.is_beacon && data.radius_m > 0) {
            map.on('load', function() {
                map.addSource('radius', {
                    type: 'geojson',
                    data: createCircle([data.lng, data.lat], data.radius_m),
                });
                map.addLayer({
                    id: 'radius-fill',
                    type: 'fill',
                    source: 'radius',
                    paint: { 'fill-color': '#22c55e', 'fill-opacity': 0.1 },
                });
                map.addLayer({
                    id: 'radius-border',
                    type: 'line',
                    source: 'radius',
                    paint: { 'line-color': '#22c55e', 'line-width': 2, 'line-opacity': 0.4 },
                });
            });
        }

        function createCircle(center, radiusM, points = 64) {
            const coords = [];
            const km = radiusM / 1000;
            for (let i = 0; i <= points; i++) {
                const angle = (i / points) * 2 * Math.PI;
                const dx = km * Math.cos(angle) / (111.32 * Math.cos(center[1] * Math.PI / 180));
                const dy = km * Math.sin(angle) / 110.574;
                coords.push([center[0] + dx, center[1] + dy]);
            }
            return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [coords] } };
        }
    });
    </script>
    @endif
</x-filament-panels::page>
