<x-filament-widgets::widget wire:poll.60s.visible>
    @php($metrics = $this->getMetrics())
    <x-filament::section heading="BGP Status" description="Latest collected RouterOS session samples. The dashboard does not connect to routers." icon="heroicon-o-share">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-5 xl:grid-cols-3">
            @foreach ([
                ['label' => 'BGP Sessions', 'value' => $metrics['total']],
                ['label' => 'Established', 'value' => $metrics['established']],
                ['label' => 'Active', 'value' => $metrics['active']],
                ['label' => 'Idle', 'value' => $metrics['idle']],
                ['label' => 'Down', 'value' => $metrics['down']],
                ['label' => 'Prefixes Received', 'value' => $metrics['prefixes_received']],
                ['label' => 'Prefixes Sent', 'value' => $metrics['prefixes_sent']],
            ] as $metric)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($metric['value']) }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($metrics['total'] === 0)
            <p class="mt-4 border-t border-gray-200 pt-3 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">No BGP sessions available.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
