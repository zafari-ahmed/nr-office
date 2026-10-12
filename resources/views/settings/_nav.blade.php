<nav class="space-y-0.5 self-start">
    @foreach (\App\Http\Controllers\SettingsController::SECTIONS as $key => [$label, $icon])
        @continue(! \App\Http\Controllers\SettingsController::allowed(auth()->user(), $key))
        <a href="{{ \App\Http\Controllers\SettingsController::url($key) }}" @class(['nav-link', 'nav-active' => ($section ?? null) === $key])><x-icon :name="$icon" />{{ $label }}</a>
    @endforeach
</nav>
