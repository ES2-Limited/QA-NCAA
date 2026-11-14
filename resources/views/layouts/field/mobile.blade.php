<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#047857">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="min-h-screen bg-zinc-100 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 flex flex-col antialiased selection:bg-emerald-500 selection:text-white pb-20">
    <!-- Top Mobile App Bar -->
    <header class="sticky top-0 z-30 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-black text-sm shadow-sm">
                ✈
            </div>
            <div>
                <div class="text-xs font-black tracking-wider uppercase text-emerald-600 dark:text-emerald-400">NCAA &bull; DSD</div>
                <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100 leading-tight">Field Auditor</div>
            </div>
        </div>

        <div class="flex items-center space-x-1">
            <livewire:notification-bell />
        </div>
    </header>

    <!-- Main Mobile Content Area -->
    <main class="flex-1 w-full max-w-lg mx-auto p-4">
        {{ $slot }}
    </main>

    <!-- Bottom Navigation Bar for Mobile -->
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-lg border-t border-zinc-200 dark:border-zinc-800 py-1.5 px-6 max-w-lg mx-auto shadow-lg">
        <div class="flex items-center justify-around">
            <!-- My Audits -->
            <a href="{{ route('field.index') }}" wire:navigate class="flex flex-col items-center py-1 px-3 text-xs font-semibold rounded-xl transition {{ request()->routeIs('field.index', 'field.audit') ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}">
                <flux:icon name="clipboard-document-check" class="size-5 mb-0.5" />
                <span>My Audits</span>
            </a>

            <!-- Notifications -->
            @php
                $unread = auth()->user()?->unreadNotifications()->count() ?? 0;
            @endphp
            <div class="flex flex-col items-center py-1 px-3 text-xs font-semibold rounded-xl text-zinc-500 dark:text-zinc-400 relative">
                <flux:icon name="bell" class="size-5 mb-0.5" />
                @if($unread > 0)
                    <span class="absolute top-1 right-4 w-2 h-2 rounded-full bg-rose-500"></span>
                @endif
                <span>Alerts</span>
            </div>

            <!-- Profile / Settings -->
            <a href="{{ route('profile.edit') }}" wire:navigate class="flex flex-col items-center py-1 px-3 text-xs font-semibold rounded-xl transition {{ request()->routeIs('profile.edit') ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}">
                <flux:icon name="user" class="size-5 mb-0.5" />
                <span>Profile</span>
            </a>
        </div>
    </nav>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => {
                    console.log('SW registration error:', err);
                });
            });
        }
    </script>
</body>
</html>
