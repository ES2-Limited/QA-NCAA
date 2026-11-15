@php
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->hasRole('field_auditor')) {
            $redirectUrl = route('field.index');
        } elseif ($user->hasRole('reviewer')) {
            $redirectUrl = route('review.index');
        } elseif ($user->hasRole('auditee')) {
            $redirectUrl = route('ncrs.index');
        } else {
            $redirectUrl = route('dashboard');
        }
    } else {
        $redirectUrl = route('login');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={{ $redirectUrl }}">
    <script>window.location.href = "{{ $redirectUrl }}";</script>
    <title>Redirecting &mdash; {{ config('app.name', 'Nigeria Civil Aviation Authority — DSD QA/IA System') }}</title>
</head>
<body class="bg-zinc-100 dark:bg-zinc-950 text-zinc-800 dark:text-zinc-200 flex items-center justify-center min-h-screen">
    <p class="text-sm">Redirecting to <a href="{{ $redirectUrl }}" class="underline font-semibold">{{ $redirectUrl }}</a>...</p>
</body>
</html>
