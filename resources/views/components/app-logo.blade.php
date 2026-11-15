@props([
    'sidebar' => false,
])

@php
    $systemName = setting('general.system_name', config('app.name', 'Nigeria Civil Aviation Authority — DSD QA/IA System'));
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$systemName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-lg bg-white p-0.5 shadow-xs border border-zinc-200/80 dark:border-zinc-700/60">
            <x-app-logo-icon class="size-8 object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$systemName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-lg bg-white p-0.5 shadow-xs border border-zinc-200/80 dark:border-zinc-700/60">
            <x-app-logo-icon class="size-8 object-contain" />
        </x-slot>
    </flux:brand>
@endif
