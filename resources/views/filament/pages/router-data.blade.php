<x-filament-panels::page>
    <div class="space-y-5">
        <x-filament::section>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $moduleTitle }}</h2>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-300">{{ $moduleDescription }}</p>
                </div>
                @if ($lastUpdatedAt)
                    <p class="text-xs text-gray-500 dark:text-gray-400">Last updated {{ $lastUpdatedAt }}</p>
                @endif
            </div>
        </x-filament::section>

        @if ($moduleKey === 'bgp-configuration')
            <x-filament::section>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        This page reads the BGP peer configuration reported by RouterOS. Editing peers is unavailable because the existing MikroTik integration exposes read operations only.
                    </p>
                    <x-filament::link :href="\App\Filament\Pages\BgpTopology::getUrl(panel: 'admin')" icon="heroicon-o-share">
                        Open BGP Topology
                    </x-filament::link>
                </div>
            </x-filament::section>
        @endif

        @if ($loadError)
            <x-filament::section>
                <p class="text-sm font-medium text-red-700 dark:text-red-300">{{ $loadError }}</p>
            </x-filament::section>
        @endif

        @if ($rows !== [])
            <x-filament::section>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-start text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                @foreach ($columns as $column)
                                    <th scope="col" class="whitespace-nowrap px-3 py-3 text-start text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $column['label'] }}</th>
                                @endforeach
                                @if (in_array($moduleKey, ['static-routes', 'alerts'], true))
                                    <th scope="col" class="px-3 py-3 text-end text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($columns as $column)
                                        <td class="max-w-72 whitespace-nowrap px-3 py-3 text-gray-800 dark:text-gray-200">
                                            @php($value = $row[$column['key']] ?? null)
                                            @if (in_array($column['key'], ['state', 'status', 'running', 'active', 'best', 'disabled', 'dynamic', 'static'], true))
                                                <span @class([
                                                    'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => in_array(strtolower((string) $value), ['online', 'established', 'true', 'yes', 'running', 'active'], true),
                                                    'bg-red-50 text-red-700 dark:bg-red-400/10 dark:text-red-300' => in_array(strtolower((string) $value), ['offline', 'down', 'false', 'no', 'disabled'], true),
                                                    'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => $value === null || ! in_array(strtolower((string) $value), ['online', 'established', 'true', 'yes', 'running', 'active', 'offline', 'down', 'false', 'no', 'disabled'], true),
                                                ])>{{ $value === null || $value === '' ? 'Not available' : $value }}</span>
                                            @else
                                                <span class="block max-w-72 truncate" title="{{ $value }}">{{ $value === null || $value === '' ? 'Not available' : $value }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    @if ($moduleKey === 'static-routes')
                                        <td class="whitespace-nowrap px-3 py-3 text-end">
                                            @if (! empty($row['.id']))
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" wire:click="setStaticRouteDisabled('{{ $row['.id'] }}', {{ in_array(strtolower((string) ($row['disabled'] ?? 'no')), ['yes', 'true', '1'], true) ? 'false' : 'true' }})" wire:confirm="Change this route's state on the MikroTik router?" wire:loading.attr="disabled">{{ in_array(strtolower((string) ($row['disabled'] ?? 'no')), ['yes', 'true', '1'], true) ? 'Enable' : 'Disable' }}</button>
                                                    <button type="button" class="text-xs font-medium text-danger-600 hover:underline dark:text-danger-400" wire:click="deleteStaticRoute('{{ $row['.id'] }}')" wire:confirm="Delete this static route from the MikroTik router?" wire:loading.attr="disabled">Delete</button>
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-500">Action unavailable</span>
                                            @endif
                                        </td>
                                    @elseif ($moduleKey === 'alerts')
                                        <td class="whitespace-nowrap px-3 py-3 text-end">
                                            @if (($row['status'] ?? '') === 'open')
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" wire:click="acknowledgeAlert({{ $row['id'] }})" wire:loading.attr="disabled">Acknowledge</button>
                                                    <button type="button" class="text-xs font-medium text-danger-600 hover:underline dark:text-danger-400" wire:click="resolveAlert({{ $row['id'] }})" wire:confirm="Resolve this alert?" wire:loading.attr="disabled">Resolve</button>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                @if ($loadError === null)
                    <div class="py-6 text-center">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">No data loaded</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose a router and use Refresh Data to request current RouterOS information. This page does not connect automatically.</p>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
