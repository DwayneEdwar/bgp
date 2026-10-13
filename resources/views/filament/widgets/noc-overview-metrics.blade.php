<x-filament-widgets::widget wire:poll.60s.visible="refreshMetrics">
    <div class="border-y border-gray-200 py-5 dark:border-white/10">
        <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-12">
            @foreach ($metricGroups as $group)
                <section @class([
                    'min-w-0',
                    '2xl:col-span-8' => $loop->first,
                    '2xl:col-span-4' => $loop->last,
                ]) aria-label="{{ $group['label'] }}">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group['label'] }}</h3>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 2xl:grid-cols-4">
                        @foreach ($group['metrics'] as $metric)
                            <div class="min-w-0 border-s border-gray-200 ps-3 dark:border-white/10">
                                <dt class="truncate text-sm font-medium text-gray-600 dark:text-gray-300">{{ $metric['label'] }}</dt>
                                <dd @class([
                                    'mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white',
                                    'text-emerald-700 dark:text-emerald-400' => $metric['label'] === 'Online Routers' && $metric['value'] > 0,
                                    'text-red-700 dark:text-red-400' => $metric['label'] === 'Offline Routers' && $metric['value'] > 0,
                                ])>{{ number_format($metric['value']) }}</dd>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $metric['note'] }}</p>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
