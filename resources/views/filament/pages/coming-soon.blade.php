<x-filament-panels::page>
    <div class="max-w-3xl border-y border-gray-200 py-8 dark:border-white/10">
        <div class="flex items-start gap-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="size-5" />
            </div>
            <div class="min-w-0">
                @if ($moduleGroup !== '')
                    <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">{{ $moduleGroup }}</p>
                @endif
                <div class="mb-3 inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">Coming Soon</div>
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Module belum tersedia</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $moduleDescription }}</p>
            </div>
        </div>
        <div class="mt-8 border-t border-gray-200 pt-5 dark:border-white/10">
            <x-filament::link :href="\App\Filament\Pages\NocDashboard::getUrl()" icon="heroicon-o-arrow-left">
                Kembali ke Dashboard
            </x-filament::link>
        </div>
    </div>
</x-filament-panels::page>
