@php
    $user = filament()->auth()->user();
@endphp

@if ($user)
    <div class="max-w-64 border-b border-gray-100 px-3 pb-3 dark:border-white/10">
        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
        <p class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
    </div>
@endif
