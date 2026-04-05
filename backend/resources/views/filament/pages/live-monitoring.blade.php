<x-filament-panels::page>
<div x-data="liveMonitor()" x-init="init()">
    {{-- Connection status --}}
    <div class="flex items-center gap-2 mb-4 text-sm text-gray-500 dark:text-gray-400">
        <span x-show="connected" class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
        <span x-show="!connected" class="inline-block w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
        <span x-text="connected ? 'Live' : 'Reconnecting...'"></span>
    </div>

    {{-- Active Ride Requests (searching / dispatched to driver) --}}
    <div wire:poll.60s>
        <x-filament::section>
            <x-slot name="heading">
                Active Ride Requests
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(live updates + 60s fallback)</span>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="py-2 px-3">ID</th>
                            <th class="py-2 px-3">Rider</th>
                            <th class="py-2 px-3">Route</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3">Dispatched To</th>
                            <th class="py-2 px-3">Waiting</th>
                            <th class="py-2 px-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->getPendingRequests() as $req)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 px-3 font-mono">{{ $req->id }}</td>
                            <td class="py-2 px-3">{{ $req->rider?->name ?? '—' }}</td>
                            <td class="py-2 px-3">{{ $req->pickupLocation?->name ?? '?' }} → {{ $req->destinationLocation?->name ?? '?' }}</td>
                            <td class="py-2 px-3">
                                @if($req->status === 'pending')
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300">Searching</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">Dispatched</span>
                                @endif
                            </td>
                            <td class="py-2 px-3">{{ $req->currentDriver?->name ?? '—' }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $req->created_at->diffForHumans(null, true) }}</td>
                            <td class="py-2 px-3">
                                {{ ($this->forceCancelRideRequestAction)(['rideRequestId' => $req->id]) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-6 px-3 text-center text-gray-400">No active ride requests</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{-- Active Rides (driver accepted and beyond) --}}
    <div wire:poll.60s class="mt-6">
        <x-filament::section>
            <x-slot name="heading">
                Active Rides
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(live updates + 60s fallback)</span>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="py-2 px-3">ID</th>
                            <th class="py-2 px-3">Rider</th>
                            <th class="py-2 px-3">Driver</th>
                            <th class="py-2 px-3">Route</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3">Elapsed</th>
                            <th class="py-2 px-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->getActiveRides() as $ride)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 px-3 font-mono">{{ $ride->id }}</td>
                            <td class="py-2 px-3">{{ $ride->rider?->name ?? '—' }}</td>
                            <td class="py-2 px-3">{{ $ride->driver?->name ?? '—' }}</td>
                            <td class="py-2 px-3">{{ $ride->pickupLocation?->name ?? '?' }} → {{ $ride->destinationLocation?->name ?? '?' }}</td>
                            <td class="py-2 px-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                    {{ str_replace('_', ' ', $ride->status) }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-gray-500">
                                {{ $ride->driver_accepted_at ? $ride->driver_accepted_at->diffForHumans(null, true) : '—' }}
                            </td>
                            <td class="py-2 px-3">
                                <div class="flex gap-2">
                                    {{ ($this->forceCompleteAction)(['rideId' => $ride->id]) }}
                                    {{ ($this->forceCancelAction)(['rideId' => $ride->id]) }}
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-6 px-3 text-center text-gray-400">No active rides</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{-- Online Drivers --}}
    <div wire:poll.60s class="mt-6">
        <x-filament::section>
            <x-slot name="heading">
                Online Drivers
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(live updates + 60s fallback)</span>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="py-2 px-3">Name</th>
                            <th class="py-2 px-3">Online Since</th>
                            <th class="py-2 px-3">Minutes Online</th>
                            <th class="py-2 px-3">Credits</th>
                            <th class="py-2 px-3">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->getOnlineDrivers() as $driver)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 px-3 font-medium">{{ $driver->name }}</td>
                            <td class="py-2 px-3">{{ $driver->driverProfile?->went_online_at?->format('H:i:s') ?? '—' }}</td>
                            <td class="py-2 px-3">{{ $driver->driverProfile?->went_online_at ? now()->diffInMinutes($driver->driverProfile->went_online_at) : '—' }}</td>
                            <td class="py-2 px-3">{{ $driver->driverProfile?->credits_balance ?? 0 }}</td>
                            <td class="py-2 px-3">{{ $driver->driverProfile?->rating_average ?? '—' }} ★</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-6 px-3 text-center text-gray-400">No drivers online</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</div>

<style>
@keyframes flash-row {
    0% { background-color: rgb(238 242 255); }
    100% { background-color: transparent; }
}
.flash-row { animation: flash-row 2s ease-out; }
</style>

<script>
function liveMonitor() {
    return {
        connected: false,
        init() {
            if (!window.Echo) return;
            const channel = window.Echo.private('admin.live');
            channel.listen('.admin.live.update', (data) => {
                const relevant = ['ride_status', 'new_request', 'request_cancelled', 'driver_status'];
                if (relevant.includes(data.type)) {
                    this.$wire.$refresh();
                    if (data.type === 'new_request') {
                        new FilamentNotification()
                            .title('New Ride Request')
                            .body(`Rider: ${data.payload.rider_name}`)
                            .info()
                            .send();
                    }
                }
            });
            channel.subscribed(() => { this.connected = true; });
            channel.error(() => { this.connected = false; });
        }
    }
}
</script>
</x-filament-panels::page>
