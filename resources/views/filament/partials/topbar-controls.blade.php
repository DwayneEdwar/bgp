@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament::icon-button
    :icon="Heroicon::OutlinedBell"
    label="Notifications are not connected yet"
    tooltip="Notifications are not connected yet"
    color="gray"
    disabled
/>

<button
    type="button"
    class="inline-flex size-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-gray-200 dark:focus-visible:outline-primary-400"
    x-on:click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark')"
    x-bind:aria-label="$store.theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
    title="Toggle light and dark mode"
>
    <x-filament::icon :icon="Heroicon::OutlinedMoon" class="size-5" x-show="$store.theme !== 'dark'" />
    <x-filament::icon :icon="Heroicon::OutlinedSun" class="size-5" x-show="$store.theme === 'dark'" x-cloak />
</button>
