<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Discover MikroTik API endpoints</x-slot>
            <x-slot name="description">Network ranges are determined from this server's network interfaces or entered manually. Only the selected API port is checked; credentials are never requested during discovery.</x-slot>

            <div class="grid gap-4 text-sm text-gray-600 dark:text-gray-300 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <p class="font-semibold text-gray-950 dark:text-white">Server-side network</p>
                    <p class="mt-1">Auto Detect uses IPv4 addresses and masks assigned to the Laravel server, not the browser or mobile device.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <p class="font-semibold text-gray-950 dark:text-white">Bounded scan</p>
                    <p class="mt-1">Scans one selected API port across a subnet no larger than 254 host addresses.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <p class="font-semibold text-gray-950 dark:text-white">Discovery result</p>
                    <p class="mt-1">An open API port is marked reachable. Register a router separately with credentials and a successful API authentication check.</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section wire:poll.5s.visible>
            <x-slot name="heading">Recent scans</x-slot>
            <x-slot name="description">Scan progress is read from persisted job state.</x-slot>

            @forelse ($this->getRecentScans() as $scan)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 py-3 last:border-0 dark:border-white/10">
                    <div class="min-w-0">
                        <p class="truncate font-mono text-sm text-gray-950 dark:text-white">{{ $scan->subnet }} · {{ implode(', ', $scan->ports ?? []) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $scan->created_at?->diffForHumans() }} · {{ number_format($scan->devices_found) }} device(s)</p>
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-semibold uppercase',
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $scan->status === 'completed',
                        'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' => in_array($scan->status, ['queued', 'running'], true),
                        'bg-red-50 text-red-700 dark:bg-red-400/10 dark:text-red-300' => $scan->status === 'failed',
                    ])>{{ $scan->status }}</span>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No scans have been run yet.</p>
            @endforelse
        </x-filament::section>

        @foreach ($this->getHeaderWidgets() as $widget)
            @livewire($widget)
        @endforeach
    </div>
</x-filament-panels::page>
