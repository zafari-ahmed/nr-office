<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tab closed · {{ \App\Models\Setting::get('company.name') }}</title>
    @include('partials.favicon')
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen grid place-items-center p-6">
    <div class="card card-pad max-w-md w-full text-center space-y-3">
        <div class="mx-auto grid place-items-center size-12 rounded-full bg-accent-soft text-accent"><x-icon name="log-out" class="!size-5" /></div>
        <h1 class="text-xl font-semibold">
            {{ match ($reason) { 'left' => 'You have left their account', 'expired' => 'This tab timed out', 'other_browser' => 'This link only works where it was opened', default => 'This "log in as" tab has ended' } }}
        </h1>
        <p class="text-[13.5px] text-muted">
            @if ($reason === 'other_browser')
                "Log in as" links work only in the browser of the Super admin who started them.
            @else
                Your other tabs are still signed in as you. You can close this tab.
            @endif
        </p>
        <div class="flex justify-center gap-2 pt-2">
            <button type="button" class="btn btn-primary" onclick="window.close()">Close tab</button>
            <a href="{{ url('/') }}" class="btn btn-secondary">Go to my dashboard</a>
        </div>
    </div>
</body>
</html>
