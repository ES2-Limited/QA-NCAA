<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $title ?? 'NCAA DSD QA/IA Portal' }}</title>
    
    <!-- PWA Manifest & Meta -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#047857">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NCAA QA/IA">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full flex flex-col font-sans antialiased text-slate-800 bg-slate-100 selection:bg-emerald-500 selection:text-white">

    <!-- Mobile Top Navigation Header -->
    <header class="sticky top-0 z-40 bg-emerald-800 text-white shadow-md border-b border-emerald-900">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('auditor.dashboard') }}" class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center font-bold text-amber-300 border border-emerald-500 shadow-inner">
                        ✈
                    </div>
                    <div>
                        <div class="text-sm font-black tracking-wider uppercase text-emerald-200">NCAA DSD</div>
                        <div class="text-xs font-semibold leading-tight text-white">Quality & Internal Audit</div>
                    </div>
                </a>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-900/60 px-2 py-1 text-xs font-medium text-emerald-200 border border-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Field PWA
                </span>
                <a href="/admin" class="text-xs bg-emerald-700 hover:bg-emerald-600 px-2.5 py-1.5 rounded-md font-semibold text-white transition shadow-sm border border-emerald-600">
                    Admin Portal &rarr;
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 pb-20 sm:pb-8">
        {{ $slot }}
    </main>

    <!-- Bottom Navigation Bar for Mobile -->
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 sm:hidden flex justify-around items-center py-2 shadow-lg">
        <a href="{{ route('auditor.dashboard') }}" class="flex flex-col items-center text-xs font-medium {{ request()->routeIs('auditor.dashboard') ? 'text-emerald-700 font-bold' : 'text-slate-500' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Audits
        </a>
        <a href="/admin/findings" class="flex flex-col items-center text-xs font-medium text-slate-500 hover:text-emerald-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Findings
        </a>
        <a href="/admin/corrective-action-plans" class="flex flex-col items-center text-xs font-medium text-slate-500 hover:text-emerald-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            CAPs
        </a>
        <a href="/admin" class="flex flex-col items-center text-xs font-medium text-slate-500 hover:text-emerald-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Filament
        </a>
    </nav>

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('NCAA Service Worker registered:', reg.scope))
                    .catch(err => console.log('Service Worker registration failed:', err));
            });
        }
    </script>
</body>
</html>
