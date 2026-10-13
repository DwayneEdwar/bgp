<x-filament-widgets::widget wire:poll.60s.visible>
    @php($neighbors = $this->getObservedNeighbors())
    <x-filament::section heading="Network Topology" description="Only neighbor relationships recently observed from RouterOS are shown." icon="heroicon-o-share">
        @if ($neighbors->isEmpty())
            <div class="flex min-h-36 flex-col items-center justify-center border border-dashed border-gray-300 px-5 py-8 text-center dark:border-white/15">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No network topology data available</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Collect router neighbor information to show verified links.</p>
            </div>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($neighbors as $neighbor)
                    <li class="grid gap-2 py-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-950 dark:text-white">{{ $neighbor->router?->name ?? 'Unmatched router' }}</p>
                            <p class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $neighbor->router?->host ?? 'Unknown host' }} · {{ $neighbor->local_interface ?: 'Interface not reported' }}</p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">{{ $neighbor->protocol ?: 'Observed neighbor' }}</span>
                        <div class="min-w-0 sm:text-end">
                            <p class="truncate font-medium text-gray-950 dark:text-white">{{ $neighbor->neighborRouter?->name ?? $neighbor->neighbor_identity ?? 'Discovered neighbor' }}</p>
                            <p class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $neighbor->neighbor_address ?: 'Address unavailable' }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4 border-t border-gray-200 pt-3 dark:border-white/10">
            <x-filament::link :href="\App\Filament\Pages\RouterData::getUrl(['module' => 'network-topology'], panel: 'admin')">Open Full Topology</x-filament::link>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
