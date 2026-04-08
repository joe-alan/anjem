<x-filament-panels::page>
<div x-data="liveMap()" x-init="init()">
    {{-- Connection status + controls --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span x-show="connected" class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
            <span x-show="!connected" class="inline-block w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
            <span x-text="connected ? 'Live' : 'Reconnecting...'"></span>
            <span class="ml-4 text-xs">
                <span x-text="drivers.length"></span> drivers online &middot;
                <span x-text="rides.length"></span> active rides
            </span>
        </div>
        <button @click="loadData()" class="text-xs px-3 py-1 rounded bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300">
            Refresh
        </button>
    </div>

    {{-- Map container --}}
    <div class="relative" wire:ignore>
        <div id="live-map" style="width:100%; height:70vh; border-radius:0.5rem;"></div>

        {{-- Detail panel --}}
        <div x-show="selected" x-transition
             class="absolute top-4 right-4 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 z-10 max-h-[60vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100" x-text="selected?.title || ''"></h3>
                <button @click="selected = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <template x-for="(value, key) in (selected?.details || {})" :key="key">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400" x-text="key"></span>
                        <span class="font-medium" x-text="value"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-6 mt-3 text-xs text-gray-500 dark:text-gray-400">
        <div class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span> Driver (in queue)
        </div>
        <div class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span> Driver (online)
        </div>
        <div class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span> Pickup
        </div>
        <div class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span> Destination
        </div>
    </div>
</div>

<link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet">
<script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>

<script>
function liveMap() {
    return {
        map: null,
        connected: false,
        selected: null,
        drivers: [],
        rides: [],
        driverMarkers: {},
        pickupMarkers: {},
        destMarkers: {},

        async init() {
            const data = await this.$wire.getMapData();
            mapboxgl.accessToken = data.mapbox_token;

            this.map = new mapboxgl.Map({
                container: 'live-map',
                style: 'mapbox://styles/mapbox/streets-v12',
                center: [110.4413, -7.0496],
                zoom: 15,
            });

            this.map.addControl(new mapboxgl.NavigationControl(), 'top-left');

            this.map.on('load', () => {
                this.applyData(data);
            });

            // 30s polling
            setInterval(() => this.loadData(), 30000);

            // WebSocket
            if (window.Echo) {
                const channel = window.Echo.private('admin.live');
                channel.listen('.admin.live.update', (evt) => {
                    const relevant = ['ride_status', 'new_request', 'request_cancelled', 'driver_status', 'driver_location'];
                    if (relevant.includes(evt.type)) {
                        this.loadData();
                    }
                });
                channel.subscribed(() => { this.connected = true; });
                channel.error(() => { this.connected = false; });
            }
        },

        async loadData() {
            const data = await this.$wire.getMapData();
            this.applyData(data);
        },

        applyData(data) {
            this.drivers = data.drivers;
            this.rides = data.rides;
            this.updateDriverMarkers();
            this.updateRideMarkers();
            this.updateRoutePolylines();
        },

        createMarkerEl(color, size = 12) {
            const el = document.createElement('div');
            el.style.width = size + 'px';
            el.style.height = size + 'px';
            el.style.borderRadius = '50%';
            el.style.backgroundColor = color;
            el.style.border = '2px solid white';
            el.style.boxShadow = '0 1px 4px rgba(0,0,0,0.3)';
            el.style.cursor = 'pointer';
            return el;
        },

        updateDriverMarkers() {
            const currentIds = new Set(this.drivers.map(d => 'd-' + d.id));

            // Remove stale
            for (const [key, marker] of Object.entries(this.driverMarkers)) {
                if (!currentIds.has(key)) {
                    marker.remove();
                    delete this.driverMarkers[key];
                }
            }

            // Add/update
            for (const driver of this.drivers) {
                const key = 'd-' + driver.id;
                const color = driver.in_queue ? '#3b82f6' : '#22c55e';

                if (this.driverMarkers[key]) {
                    this.driverMarkers[key].setLngLat([driver.lng, driver.lat]);
                } else {
                    const el = this.createMarkerEl(color, 14);
                    const marker = new mapboxgl.Marker({ element: el })
                        .setLngLat([driver.lng, driver.lat])
                        .addTo(this.map);

                    el.addEventListener('click', () => {
                        const onlineSince = new Date(driver.online_since);
                        const mins = Math.round((Date.now() - onlineSince) / 60000);
                        this.selected = {
                            title: driver.name,
                            details: {
                                'Vehicle': driver.vehicle_type,
                                'Credits': driver.credits,
                                'Rating': driver.rating + ' ★',
                                'Queue': driver.in_queue ? 'In Queue' : 'Not in Queue',
                                'Online': mins + ' min',
                            }
                        };
                    });

                    this.driverMarkers[key] = marker;
                }
            }
        },

        updateRideMarkers() {
            const currentIds = new Set(this.rides.map(r => 'r-' + r.id));

            // Remove stale pickup/dest markers
            for (const [key, marker] of Object.entries(this.pickupMarkers)) {
                if (!currentIds.has(key)) {
                    marker.remove();
                    delete this.pickupMarkers[key];
                }
            }
            for (const [key, marker] of Object.entries(this.destMarkers)) {
                if (!currentIds.has(key)) {
                    marker.remove();
                    delete this.destMarkers[key];
                }
            }

            for (const ride of this.rides) {
                const key = 'r-' + ride.id;

                const showDetail = () => {
                    this.selected = {
                        title: 'Ride #' + ride.id,
                        details: {
                            'Rider': ride.rider_name,
                            'Driver': ride.driver_name,
                            'Status': ride.status,
                            'Pickup': ride.pickup.name,
                            'Destination': ride.destination.name,
                            'Fare': ride.fare ? 'Rp ' + ride.fare.toLocaleString() : '-',
                        }
                    };
                };

                // Pickup marker
                if (ride.pickup.lat && ride.pickup.lng) {
                    if (this.pickupMarkers[key]) {
                        this.pickupMarkers[key].setLngLat([ride.pickup.lng, ride.pickup.lat]);
                    } else {
                        const el = this.createMarkerEl('#10b981', 10);
                        el.addEventListener('click', showDetail);
                        this.pickupMarkers[key] = new mapboxgl.Marker({ element: el })
                            .setLngLat([ride.pickup.lng, ride.pickup.lat])
                            .addTo(this.map);
                    }
                }

                // Destination marker
                if (ride.destination.lat && ride.destination.lng) {
                    if (this.destMarkers[key]) {
                        this.destMarkers[key].setLngLat([ride.destination.lng, ride.destination.lat]);
                    } else {
                        const el = this.createMarkerEl('#ef4444', 10);
                        el.addEventListener('click', showDetail);
                        this.destMarkers[key] = new mapboxgl.Marker({ element: el })
                            .setLngLat([ride.destination.lng, ride.destination.lat])
                            .addTo(this.map);
                    }
                }
            }
        },

        updateRoutePolylines() {
            // Remove old route layers/sources
            const style = this.map.getStyle();
            if (style && style.layers) {
                style.layers.forEach(layer => {
                    if (layer.id.startsWith('route-')) {
                        this.map.removeLayer(layer.id);
                    }
                });
                Object.keys(style.sources || {}).forEach(src => {
                    if (src.startsWith('route-')) {
                        this.map.removeSource(src);
                    }
                });
            }

            // Add route polylines for in_progress rides with geometry
            for (const ride of this.rides) {
                if (ride.status !== 'in_progress' || !ride.route_geometry) continue;

                const sourceId = 'route-' + ride.id;
                const layerId = 'route-' + ride.id;

                let geojson = ride.route_geometry;
                if (typeof geojson === 'string') {
                    try { geojson = JSON.parse(geojson); } catch(e) { continue; }
                }

                // Wrap in Feature if it's a raw geometry
                const feature = geojson.type === 'Feature' ? geojson : {
                    type: 'Feature',
                    geometry: geojson,
                    properties: {}
                };

                this.map.addSource(sourceId, {
                    type: 'geojson',
                    data: feature,
                });

                this.map.addLayer({
                    id: layerId,
                    type: 'line',
                    source: sourceId,
                    layout: { 'line-join': 'round', 'line-cap': 'round' },
                    paint: {
                        'line-color': '#6366f1',
                        'line-width': 3,
                        'line-opacity': 0.7,
                    },
                });
            }
        },
    }
}
</script>
</x-filament-panels::page>
