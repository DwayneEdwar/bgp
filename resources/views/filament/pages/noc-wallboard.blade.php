<x-filament-panels::page class="bg-gray-950 text-white">
    <div wire:poll.{{ $this->getRefreshSeconds() }}s.visible class="space-y-5">
        @php($metrics = $this->getWallboardMetrics())
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Network Operations Center</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white md:text-5xl">Live Wallboard</h1>
            </div>
            <div class="text-end">
                <p class="text-xs uppercase tracking-wide text-gray-400">Updated from collected records</p>
                <p class="mt-1 text-lg font-semibold text-gray-100">{{ now()->format('Y-m-d H:i:s') }}</p>
            </div>
        </div>

        <section aria-label="Network metrics" class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach ([
                ['label' => 'Routers', 'value' => $metrics['routers'], 'color' => 'text-white'],
                ['label' => 'Online', 'value' => $metrics['online'], 'color' => 'text-emerald-300'],
                ['label' => 'Offline', 'value' => $metrics['offline'], 'color' => 'text-red-300'],
                ['label' => 'Unknown', 'value' => $metrics['unknown'], 'color' => 'text-gray-300'],
                ['label' => 'Open Alerts', 'value' => $metrics['alerts'], 'color' => $metrics['critical'] > 0 ? 'text-red-300' : 'text-amber-300'],
            ] as $metric)
                <div class="rounded-lg border border-white/10 bg-gray-900 p-4 md:p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400 md:text-sm">{{ $metric['label'] }}</p>
                    <p class="mt-3 text-4xl font-semibold tabular-nums md:text-6xl {{ $metric['color'] }}">{{ number_format($metric['value']) }}</p>
                </div>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-lg border border-white/10 bg-gray-900 p-5 md:p-7">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-white md:text-2xl">Observed Network Links</h2>
                    <a class="text-sm font-medium text-amber-300 hover:text-amber-200" href="{{ \App\Filament\Pages\RouterData::getUrl(['module' => 'network-topology'], panel: 'admin') }}">Full topology</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($this->getObservedLinks() as $link)
                        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 rounded-md border border-white/5 bg-gray-950/70 p-4">
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-white">{{ $link->router?->name ?? 'Unknown Router' }}</p>
                                <p class="truncate font-mono text-xs text-gray-400">{{ $link->router?->host ?? 'Unknown address' }}</p>
                            </div>
                            <span class="text-xs font-bold uppercase tracking-wide text-sky-300">{{ $link->protocol ?: 'Neighbor' }}</span>
                            <div class="min-w-0 text-end">
                                <p class="truncate text-base font-semibold text-white">{{ $link->neighborRouter?->name ?? $link->neighbor_identity ?? 'Unmatched neighbor' }}</p>
                                <p class="truncate font-mono text-xs text-gray-400">{{ $link->neighbor_address ?? 'Address unavailable' }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="flex min-h-32 items-center justify-center rounded-md border border-dashed border-white/15 text-center text-sm text-gray-400">No network topology data available.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-white/10 bg-gray-900 p-5 md:p-7">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-white md:text-2xl">BGP Sessions</h2>
                    <a class="text-sm font-medium text-amber-300 hover:text-amber-200" href="{{ \App\Filament\Pages\RouterData::getUrl(['module' => 'bgp-topology'], panel: 'admin') }}">BGP topology</a>
                </div>
                <p class="mt-2 text-sm text-gray-400">{{ $metrics['bgp_established'] }} established · {{ $metrics['bgp_down'] }} down/idle</p>
                <div class="mt-4 space-y-3">
                    @forelse ($this->getLatestBgpSessions() as $session)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-white/5 bg-gray-950/70 p-4">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-white">{{ $session->router?->name ?? 'Unknown router' }} · {{ $session->peer_name ?? 'Peer' }}</p>
                                <p class="truncate font-mono text-xs text-gray-400">{{ $session->remote_address ?? 'Remote address unavailable' }} · AS {{ $session->remote_as ?? 'N/A' }}</p>
                            </div>
                            <span @class([
                                'rounded-full px-3 py-1 text-xs font-semibold uppercase',
                                'bg-emerald-400/10 text-emerald-300' => $session->state === 'established',
                                'bg-red-400/10 text-red-300' => in_array($session->state, ['down', 'idle', 'connect'], true),
                                'bg-gray-700 text-gray-200' => ! in_array($session->state, ['established', 'down', 'idle', 'connect'], true),
                            ])>{{ $session->state }}</span>
                        </div>
                    @empty
                        <div class="flex min-h-32 items-center justify-center rounded-md border border-dashed border-white/15 text-center text-sm text-gray-400">No BGP sessions available.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-white/10 bg-gray-900 p-5 md:p-7">
            <h2 class="text-lg font-semibold text-white md:text-2xl">Recent Events</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @forelse ($this->getLatestEvents() as $event)
                    <article class="rounded-md border border-white/5 bg-gray-950/70 p-4">
                        <p class="text-sm font-medium text-white">{{ $event->description }}</p>
                        <p class="mt-2 text-xs text-gray-400">{{ $event->router?->name ?? 'Network' }} · {{ $event->created_at?->diffForHumans() }}</p>
                    </article>
                @empty
                    <p class="py-4 text-sm text-gray-400">No recent network events.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
