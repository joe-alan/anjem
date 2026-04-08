<x-filament-panels::page>
    <div wire:poll.30s>

        {{-- Service Health Checks --}}
        <x-filament::section>
            <x-slot name="heading">Service Health</x-slot>

            @php $checks = $this->getServiceChecks(); @endphp

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                @foreach(['database' => 'PostgreSQL', 'redis' => 'Redis', 'reverb' => 'Reverb WS', 'storage' => 'Storage', 'queue' => 'Queue'] as $key => $label)
                @php
                    $check = $checks[$key];
                    $isOk = $check['status'] === 'ok';
                    $isDegraded = ($check['status'] ?? '') === 'degraded';
                @endphp
                <div class="p-4 rounded-lg border {{ $isOk ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : ($isDegraded ? 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950') }}">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-2.5 h-2.5 rounded-full {{ $isOk ? 'bg-green-500' : ($isDegraded ? 'bg-yellow-500 animate-pulse' : 'bg-red-500 animate-pulse') }}"></span>
                        <span class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $label }}</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if(isset($check['latency']) && $check['latency'] !== '-')
                            {{ $check['latency'] }}
                        @elseif(!$isOk)
                            Unreachable
                        @endif
                        @if(isset($check['channels']))
                            · {{ $check['channels'] }} ch
                        @endif
                        @if(isset($check['disk']))
                            · {{ $check['disk'] }}
                        @endif
                        @if(isset($check['failed_1h']) && $check['failed_1h'] > 0)
                            · {{ $check['failed_1h'] }} failed/1h
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Real-Time Metrics --}}
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">Real-Time Metrics</x-slot>

                @php $metrics = $this->getApplicationMetrics(); @endphp

                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
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
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $metrics['in_queue'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">In Queue</div>
                    </div>
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $metrics['completed_today'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Completed Today</div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Queue & Failed Jobs --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-filament::section>
                <x-slot name="heading">Queue Health</x-slot>

                @php $queue = $this->getQueueHealth(); @endphp

                <div class="grid grid-cols-2 gap-4 text-center">
                    <div class="p-4 rounded-lg {{ $queue['failed_24h'] > 0 ? 'bg-red-50 dark:bg-red-900/20' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <div class="text-2xl font-bold {{ $queue['failed_24h'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">{{ $queue['failed_24h'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Failed (24h)</div>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $queue['failed_total'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Failed (Total)</div>
                    </div>
                </div>

                @if($queue['failed_24h'] > 0)
                    <div class="mt-3">
                        <a href="{{ route('filament.admin.resources.failed-jobs.index') }}" class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 underline">
                            View failed jobs →
                        </a>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Route Cache</x-slot>

                @php $cache = $this->getCacheHealth(); @endphp

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $cache['total_routes'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Total Cached</div>
                    </div>
                    <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $cache['fresh_routes'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Fresh</div>
                    </div>
                    <div class="p-4 rounded-lg {{ $cache['stale_routes'] > 0 ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <div class="text-2xl font-bold {{ $cache['stale_routes'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-600 dark:text-gray-400' }}">{{ $cache['stale_routes'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Stale</div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Platform Stats --}}
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">Platform</x-slot>

                @php $metrics = $this->getApplicationMetrics(); @endphp

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $metrics['total_users'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Total Users</div>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $metrics['total_drivers'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Total Drivers</div>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $metrics['verified_drivers'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Verified Drivers</div>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $metrics['completed_total'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">Rides Completed (All Time)</div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Reverb Channels --}}
        <div class="mt-6">
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">Reverb Channels</x-slot>

                @php $channels = $this->getReverbChannels(); @endphp

                @if(empty($channels))
                    <p class="text-sm text-gray-400 italic">No active channels — Reverb may be down or no clients connected.</p>
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
        </div>

    </div>
</x-filament-panels::page>
