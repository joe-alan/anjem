<x-filament-panels::page>
<div wire:poll.30s>
    <x-filament::section>
        <x-slot name="heading">
            Dispatch Timeline
            <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(auto-refresh 30s)</span>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="py-2 px-3">Request</th>
                        <th class="py-2 px-3">Rider</th>
                        <th class="py-2 px-3">Route</th>
                        <th class="py-2 px-3">Driver Offered</th>
                        <th class="py-2 px-3">Dispatched At</th>
                        <th class="py-2 px-3">Response</th>
                        <th class="py-2 px-3">Response Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->getRecentDispatches() as $attempt)
                    <tr class="border-b dark:border-gray-700">
                        <td class="py-2 px-3 font-mono text-xs">#{{ $attempt->ride_request_id }}</td>
                        <td class="py-2 px-3">{{ $attempt->rideRequest?->rider?->name ?? '—' }}</td>
                        <td class="py-2 px-3">
                            {{ $attempt->rideRequest?->pickupLocation?->name ?? '?' }}
                            →
                            {{ $attempt->rideRequest?->destinationLocation?->name ?? '?' }}
                        </td>
                        <td class="py-2 px-3">{{ $attempt->driver?->name ?? '—' }}</td>
                        <td class="py-2 px-3 text-gray-500 font-mono text-xs">{{ $attempt->dispatched_at->format('H:i:s') }}</td>
                        <td class="py-2 px-3">
                            @php
                                $colors = [
                                    'pending'   => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                    'accepted'  => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                    'declined'  => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                                    'timed_out' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                ];
                                $colorClass = $colors[$attempt->response] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $colorClass }}">
                                {{ str_replace('_', ' ', ucfirst($attempt->response)) }}
                            </span>
                        </td>
                        <td class="py-2 px-3 text-gray-500 font-mono text-xs">
                            @if($attempt->responded_at)
                                {{ $attempt->getResponseTimeSeconds() }}s
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-6 px-3 text-center text-gray-400">No dispatch attempts recorded yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</div>
</x-filament-panels::page>
