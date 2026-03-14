<x-filament-panels::page>
<div x-data="driverQueue()" x-init="init()">
    {{-- Connection status --}}
    <div class="flex items-center gap-2 mb-4 text-sm text-gray-500 dark:text-gray-400">
        <span x-show="connected" class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
        <span x-show="!connected" class="inline-block w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
        <span x-text="connected ? 'Live' : 'Reconnecting...'"></span>
    </div>

    {{-- Stats bar --}}
    @php $stats = $this->getQueueStats(); @endphp
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-center">
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $stats['total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Total in Queue</div>
        </div>
        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['avg_wait_minutes'] }}m</div>
            <div class="text-xs text-gray-500 mt-1">Average Wait</div>
        </div>
        <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-center">
            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['in_cooldown'] }}</div>
            <div class="text-xs text-gray-500 mt-1">In Cooldown</div>
        </div>
    </div>

    {{-- Queue table --}}
    <div wire:poll.60s>
        <x-filament::section>
            <x-slot name="heading">
                FIFO Queue
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(live updates + 60s fallback)</span>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="py-2 px-3">#</th>
                            <th class="py-2 px-3">Driver</th>
                            <th class="py-2 px-3">Joined At</th>
                            <th class="py-2 px-3">Wait Time</th>
                            <th class="py-2 px-3">Declines</th>
                            <th class="py-2 px-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->getQueuedDrivers() as $index => $driver)
                        <tr
                            class="border-b dark:border-gray-700 transition-all"
                            :class="{'ring-2 ring-indigo-500 ring-inset animate-pulse': dispatchedDriverId === {{ $driver->user_id }}}"
                        >
                            <td class="py-2 px-3 font-mono text-gray-500">{{ $index + 1 }}</td>
                            <td class="py-2 px-3 font-medium">{{ $driver->user?->name ?? '—' }}</td>
                            <td class="py-2 px-3">{{ $driver->queue_joined_at?->format('H:i:s') ?? '—' }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $driver->queue_joined_at ? now()->diffInMinutes($driver->queue_joined_at) . 'm' : '—' }}</td>
                            <td class="py-2 px-3">
                                @php $declines = $driver->decline_count ?? 0; @endphp
                                <span class="font-medium {{ $declines >= 3 ? 'text-red-600' : ($declines >= 1 ? 'text-yellow-600' : 'text-gray-600') }}">
                                    {{ $declines }}
                                </span>
                            </td>
                            <td class="py-2 px-3">
                                @if($driver->isInCooldown())
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300">
                                        Cooldown until {{ $driver->queue_cooldown_until?->format('H:i') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                                        Waiting
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 px-3 text-center text-gray-400">No drivers in queue</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</div>

<script>
function driverQueue() {
    return {
        connected: false,
        dispatchedDriverId: null,
        init() {
            if (!window.Echo) return;
            const channel = window.Echo.private('admin.live');
            channel.listen('.admin.live.update', (data) => {
                if (data.type === 'driver_status' && data.payload.is_online) {
                    this.$wire.$refresh();
                }
                if (data.type === 'matching_dispatch') {
                    this.dispatchedDriverId = data.payload.driver_ids[0] ?? null;
                    setTimeout(() => { this.dispatchedDriverId = null; }, 8000);
                    this.$wire.$refresh();
                }
                if (data.type === 'driver_rejoined') {
                    this.$wire.$refresh();
                }
            });
            channel.subscribed(() => { this.connected = true; });
            channel.error(() => { this.connected = false; });
        }
    }
}
</script>
</x-filament-panels::page>
