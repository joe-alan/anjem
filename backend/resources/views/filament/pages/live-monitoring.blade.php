<x-filament-panels::page>
    {{-- Active Rides --}}
    <div wire:poll.10s>
        <x-filament::section>
            <x-slot name="heading">
                Active Rides
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(auto-refreshes every 10s)</span>
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->getActiveRides() as $ride)
                        <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
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
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 px-3 text-center text-gray-400">No active rides</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{-- Online Drivers --}}
    <div wire:poll.15s class="mt-6">
        <x-filament::section>
            <x-slot name="heading">
                Online Drivers
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(auto-refreshes every 15s)</span>
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
                        <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
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
</x-filament-panels::page>
