<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($settings as $key => $value)
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ str($key)->replace('_', ' ')->title() }}</p>
                <p class="mt-2 break-words text-sm font-medium text-gray-950 dark:text-white">{{ $value === '' ? 'Not configured' : $value }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-5 text-xs text-gray-500 dark:text-gray-400">Settings contain application preferences only. Router credentials are stored on Router records and are not available here.</p>
</x-filament-panels::page>
