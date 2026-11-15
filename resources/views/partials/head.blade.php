<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $siteName = setting('general.system_name', config('app.name', 'Nigeria Civil Aviation Authority — DSD QA/IA System'));
    $primaryColor = setting('general.primary_color', '#2a1770');
    $accentColor = setting('general.accent_color', '#008751');
@endphp

<title>
    {{ filled($title ?? null) ? $title.' - '.$siteName : $siteName }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<style>
    :root {
        --ncaa-primary: {{ $primaryColor }};
        --ncaa-primary-500: {{ $primaryColor }};
        --color-primary: {{ $primaryColor }};
        --color-primary-500: {{ $primaryColor }};
        --ncaa-accent: {{ $accentColor }};
        --ncaa-accent-500: {{ $accentColor }};
        --color-accent: {{ $accentColor }};
        --color-accent-500: {{ $accentColor }};
    }
</style>
