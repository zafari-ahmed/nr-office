<nav class="card p-2 space-y-0.5 self-start">
    @foreach ($sections as $key => [$label, $icon])
        <a href="{{ $key === 'users' ? route('settings.users.index') : route('settings.show', $key) }}" @class(['nav-link', 'nav-active' => $section === $key])><x-icon :name="$icon" />{{ $label }}</a>
    @endforeach
    <div class="px-3 pt-3 pb-1 text-[11px] text-muted">Coming next: notifications, backup</div>
</nav>
