<x-filament-widgets::widget wire:poll.60s.visible>
    @php($events = $this->getRecentEvents())
    <x-filament::section heading="Recent Events" description="Recent events collected from user actions and monitoring." icon="heroicon-o-clock">
        @if ($events->isEmpty())
            <div class="flex min-h-36 flex-col items-center justify-center border border-dashed border-gray-300 px-5 py-8 text-center dark:border-white/15">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No recent network events</p>
            </div>
        @else
            <ol class="space-y-4">
                @foreach ($events as $event)
                    <li class="flex gap-3">
                        <span class="mt-1 size-2 shrink-0 rounded-full {{ str_contains($event->type, 'offline') ? 'bg-red-500' : 'bg-sky-500' }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $event->description }}</p>
                            <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $event->router?->name ?? 'Network' }} · {{ $event->created_at?->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
