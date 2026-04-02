<x-filament-panels::page>
    <div wire:poll.30s>
        {{-- Reverb WebSockets --}}
        <x-filament::section>
            <x-slot name="heading">Reverb WebSockets</x-slot>

            @php $channels = $this->getReverbChannels(); @endphp

            @if(empty($channels))
                <p class="text-sm text-gray-400 italic">Unavailable — Reverb may be down or unreachable.</p>
            @else
                <p class="text-sm text-gray-500 mb-3">{{ count($channels) }} active channel(s)</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="py-2 px-3">Channel</th>
                                <th class="py-2 px-3">Subscribers</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($channels as $name => $info)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-3 font-mono text-xs">{{ $name }}</td>
                                <td class="py-2 px-3">{{ $info['subscription_count'] ?? 0 }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Application Stats --}}
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">Application Stats</x-slot>

                @php $metrics = $this->getApplicationMetrics(); @endphp

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $metrics['pending_requests'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Pending Requests</div>
                    </div>
                    <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $metrics['active_rides'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Active Rides</div>
                    </div>
                    <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $metrics['online_drivers'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Online Drivers</div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
