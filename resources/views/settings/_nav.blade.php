<nav class="space-y-0.5 self-start">
    @foreach ($sections as $key => [$label, $icon])
        <a href="{{ $key === 'users' ? route('settings.users.index') : route('settings.show', $key) }}" @class(['nav-link', 'nav-active' => $section === $key])><x-icon :name="$icon" />{{ $label }}</a>
    @endforeach
    @foreach (['Notifications' => 'bell', 'Backup' => 'database'] as $label => $icon)
        <span class="nav-link cursor-default opacity-60 hover:bg-transparent" title="Coming next in the plan"><x-icon :name="$icon" />{{ $label }}<span class="ml-auto text-[11px] text-muted">Soon</span></span>
    @endforeach
</nav>
