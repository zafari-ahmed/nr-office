<nav class="space-y-0.5 self-start">
    @foreach ($sections as $key => [$label, $icon])
        <a href="{{ $key === 'users' ? route('settings.users.index') : route('settings.show', $key) }}" @class(['nav-link', 'nav-active' => $section === $key])><x-icon :name="$icon" />{{ $label }}</a>
    @endforeach
</nav>
