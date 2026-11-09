<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ \App\Models\Setting::get('company.name') }}</title>
    @if (file_exists(public_path('images/favicon.png')))<link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}?v={{ filemtime(public_path('images/favicon.png')) }}">@endif
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen grid lg:grid-cols-2">
    <div class="hidden lg:flex flex-col justify-between p-12 bg-accent text-white dark:text-gray-900">
        <div class="flex items-center gap-3">
            <span class="grid place-items-center size-10 rounded-xl bg-white/15 font-bold">NR</span>
            <span class="font-semibold text-lg">{{ \App\Models\Setting::get('company.name') }}</span>
        </div>
        <div>
            <div class="text-xs font-medium uppercase tracking-[0.2em] opacity-70 mb-4">Internal Office Portal</div>
            <h1 class="text-3xl font-semibold leading-tight max-w-md">Engineering excellence, managed with precision.</h1>
            <p class="mt-4 opacity-80 max-w-md">One secure workspace for project delivery, commercial records, finance, personnel and site deployment across NR &amp; Associates.</p>
        </div>
        <div class="text-sm opacity-70">{{ \App\Models\Setting::get('company.tagline') }}</div>
    </div>
    <div class="flex items-center justify-center p-6">
        <form method="POST" action="{{ url('/login') }}" class="w-full max-w-sm space-y-5" style="margin-top: -25%;">
            @csrf
            @if (file_exists(public_path('images/nr-logo.png')))
                <img src="{{ asset('images/nr-logo.png') }}" alt="{{ \App\Models\Setting::get('company.name') }}" class="mx-auto h-40 w-auto" style="width: 40%;">
            @endif
            <div>
                <h2 class="text-2xl font-semibold">Welcome back</h2>
                <p class="text-muted mt-1">Sign in to the office system.</p>
            </div>
            <x-field label="Username or email" name="login" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" />
            <x-field label="Password" name="password" type="password" required autocomplete="current-password" />
            <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="remember" class="checkbox"> Keep me signed in</label>
            <button class="btn btn-primary w-full h-10">Sign in</button>
        </form>
    </div>
</body>
</html>
