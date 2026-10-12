@php
    $user = auth()->user();
    // [label, route, icon, active patterns, badge, badge tone, permission needed (null = everyone)]
    $nav = collect([
        ['Dashboard', 'dashboard', 'layout-dashboard', ['dashboard'], 0, '', null],
        ['Projects', 'projects.index', 'folder-kanban', ['projects.*', 'clients.*'], 0, '', null],
        ['Quotations', 'quotations.index', 'file-text', ['quotations.*', 'document-logs.*'], 0, '', 'quotations.view'],
        ['Invoices', 'invoices.index', 'receipt', ['invoices.*'], $shellOverdueInvoices ?? 0, 'red', 'invoices.view'],
        [$user->hasPermission('expenses.view_all') ? 'Expenses' : 'My expenses', 'expenses.index', 'wallet', ['expenses.*'], $shellPendingExpenses ?? 0, 'amber', null],
        ['Inventory', 'inventory.index', 'package', ['inventory.*'], 0, '', 'inventory.view'],
        ['Employees', 'employees.index', 'users', ['employees.*'], 0, '', 'employees.view'],
        ['Site Rotation', 'rotation.index', 'calendar-range', ['rotation.*'], $shellOverdueVisits ?? 0, 'red', 'rotation.view'],
        ['Reports', 'reports.index', 'bar-chart-3', ['reports.*'], 0, '', 'reports.view'],
    ])->filter(fn ($item) => ! $item[6] || $user->hasPermission($item[6]))->values();
    $canSettings = collect(array_keys(\App\Http\Controllers\SettingsController::SECTIONS))->contains(fn ($s) => \App\Http\Controllers\SettingsController::allowed($user, $s));
    // "+ New" menu: [label, icon, link or drawer, permission]
    $newItems = collect([
        ['Project', 'folder-kanban', route('projects.create'), 'projects.manage'],
        ['Quotation', 'file-text', route('quotations.create'), 'quotations.manage'],
        ['Invoice', 'receipt', route('invoices.create'), 'invoices.manage'],
        ['Expense', 'wallet', 'drawer:expense', null],
        ['Follow-up', 'phone-call', 'drawer:follow-up', null],
        ['Log sent document', 'mail', 'drawer:log-document', 'quotations.manage'],
    ])->filter(fn ($item) => ! $item[3] || $user->hasPermission($item[3]))->values();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Models\Setting::get('company.name') }} Office</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        try {
            // Apply the remembered sidebar width before first paint so pages don't shift on load.
            if (localStorage.getItem('nr-sidebar') === '1') document.documentElement.classList.add('sb-collapsed');
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div x-data class="flex min-h-screen">

    {{-- Sidebar --}}
    <div x-cloak x-show="$store.sidebar.mobileOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/30 lg:hidden" @click="$store.sidebar.mobileOpen = false"></div>
    <aside :class="$store.sidebar.mobileOpen && 'is-open'" class="app-sidebar no-print fixed inset-y-0 left-0 z-50 flex flex-col bg-side border-r border-line">
        <div class="flex items-center gap-2.5 h-14 px-4 border-b border-line">
            <span class="grid place-items-center size-[30px] rounded-lg bg-accent text-on-accent font-bold text-[12px] tracking-[.02em] shrink-0">NR</span>
            <div class="sb-label min-w-0">
                <div class="text-[14px] font-semibold leading-tight truncate">{{ \App\Models\Setting::get('company.name') }}</div>
                <div class="text-[12px] text-muted leading-tight">Office</div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto px-2.5 py-2.5 space-y-0.5">
            @foreach ($nav as [$label, $route, $icon, $patterns, $badge, $tone])
                <a href="{{ route($route) }}" @class(['nav-link', 'nav-active' => request()->routeIs(...$patterns)]) title="{{ $label }}">
                    <x-icon :name="$icon" />
                    <span class="sb-label flex-1 truncate">{{ $label }}</span>
                    @if ($badge > 0)
                        <span class="sb-label count bg-{{ $tone }}-bg text-{{ $tone }}-fg">{{ $badge }}</span>
                        <span class="sb-dot absolute top-1.5 right-2 size-2 rounded-full bg-{{ $tone }}-fg"></span>
                    @endif
                </a>
            @endforeach
            @if ($canSettings)
                <a href="{{ route('settings.index') }}" @class(['nav-link', 'nav-active' => request()->routeIs('settings.*')]) title="Settings">
                    <x-icon name="settings" /><span class="sb-label">Settings</span>
                </a>
            @endif
        </nav>
        <div class="px-2.5 py-2.5 border-t border-line">
            <button type="button" class="hidden lg:flex nav-link w-full" @click="$store.sidebar.toggle()" title="Collapse sidebar">
                <x-icon name="panel-left-close" class="sb-label" />
                <x-icon name="panel-left-open" class="sb-dot" />
                <span class="sb-label">Collapse</span>
            </button>
            <div class="sb-user flex items-center gap-2.5 px-1.5 pt-2">
                <x-avatar :name="$user->name" :src="$user->employee?->photoUrl()" size="size-[30px] text-[11.5px]" />
                <div class="sb-label min-w-0 flex-1">
                    <div class="text-[13px] font-medium truncate">{{ $user->name }}</div>
                    <div class="text-[11.5px] text-muted">{{ \App\Models\User::ROLES[$user->role] ?? $user->role }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="sb-label">
                    @csrf
                    <button class="btn btn-icon !size-7 !w-7" title="Sign out"><x-icon name="log-out" class="!size-4" /></button>
                </form>
            </div>
        </div>
    </aside>

    <div class="app-main flex-1 min-w-0 flex flex-col">

        {{-- Top bar --}}
        <header class="no-print sticky top-0 z-30 h-14 flex items-center gap-3 px-4 lg:px-5 bg-surface border-b border-line">
            <button class="btn btn-icon lg:hidden" @click="$store.sidebar.mobileOpen = true"><x-icon name="menu" /></button>

            <div x-data="palette" class="flex-1 max-w-[420px]">
                <button @click="show()" class="w-full flex items-center gap-2 h-[34px] px-3 rounded-lg border border-line bg-bg text-muted text-[13.5px] hover:border-accent/40">
                    <x-icon name="search" />
                    <span class="flex-1 text-left truncate"><span class="sm:hidden">Search…</span><span class="hidden sm:inline">Search projects, people, invoices…</span></span>
                    <kbd class="hidden sm:inline font-mono text-[11px] bg-surface border border-line rounded px-1.5 py-px">Ctrl K</kbd>
                </button>
                <div x-cloak x-show="open" class="fixed inset-0 z-[60] flex items-start justify-center pt-[12vh] px-4 bg-gray-900/30" @click.self="open = false">
                    <div class="card w-full max-w-xl overflow-hidden" @click.outside="open = false">
                        <div class="flex items-center gap-2 px-4 border-b border-line">
                            <x-icon name="search" class="text-muted" />
                            <input x-ref="input" x-model="q" @input="search()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                                   class="flex-1 h-12 bg-transparent outline-none text-sm" placeholder="Type to search…">
                            <kbd class="text-[11px] text-muted border border-line rounded px-1.5">Esc</kbd>
                        </div>
                        <ul class="max-h-80 overflow-y-auto p-2">
                            <template x-for="(r, i) in results" :key="r.url">
                                <li>
                                    <a :href="r.url" :class="i === active ? 'bg-accent-soft' : ''" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2" @mouseenter="active = i">
                                        <span class="min-w-0"><span class="block truncate font-medium" x-text="r.title"></span><span class="block text-xs text-muted truncate" x-text="r.meta"></span></span>
                                        <span class="text-[11px] text-muted" x-text="r.group"></span>
                                    </a>
                                </li>
                            </template>
                            <li x-show="q.length >= 2 && !results.length" class="px-3 py-6 text-center text-sm text-muted">No matches yet. Press Enter for full search.</li>
                            <li x-show="q.length < 2" class="px-3 py-6 text-center text-sm text-muted">Search by project name or code, client, employee, CNIC, quotation or invoice number.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1.5 ml-auto">
                <div x-data="{ open: false }" class="relative">
                    <button class="btn btn-icon relative" @click="open = !open" title="Notifications">
                        <x-icon name="bell" class="size-[18px]" />
                        @if ($shellUnread > 0)
                            <span class="absolute top-1 right-1 min-w-4 h-4 px-1 rounded-full bg-red-fg text-white ring-2 ring-surface text-[10px] leading-4 font-semibold">{{ $shellUnread > 9 ? '9+' : $shellUnread }}</span>
                        @endif
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] card z-40 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-line">
                            <span class="font-semibold">Notifications</span>
                            @if ($shellUnread)
                                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-xs link">Mark all read</button></form>
                            @endif
                        </div>
                        <ul class="max-h-96 overflow-y-auto divide-y divide-line">
                            @forelse ($shellNotifications as $n)
                                <li>
                                    <a href="{{ route('notifications.open', $n->id) }}" @class(['flex gap-3 px-4 py-3 hover:bg-surface-2', 'bg-accent-soft/40' => ! $n->read_at])>
                                        <span class="pill-{{ $n->data['tone'] ?? 'blue' }} grid place-items-center size-8 rounded-full shrink-0"><x-icon :name="$n->data['icon'] ?? 'bell'" class="size-4" /></span>
                                        <span class="min-w-0">
                                            <span class="block text-[13px] font-medium">{{ $n->data['title'] ?? '' }}</span>
                                            <span class="block text-xs text-muted">{{ $n->data['body'] ?? '' }}</span>
                                            <span class="block text-[11px] text-muted mt-0.5">{{ $n->created_at->diffForHumans() }}</span>
                                        </span>
                                    </a>
                                </li>
                            @empty
                                <li><x-empty icon="bell" title="You're all caught up" text="Reminders for follow-ups, returns and overdue invoices show up here." /></li>
                            @endforelse
                        </ul>
                        <a href="{{ route('notifications.index') }}" class="block text-center text-xs link py-2.5 border-t border-line">View all</a>
                    </div>
                </div>

                <div x-data="{ open: false }" class="relative">
                    <button class="btn btn-primary" @click="open = !open"><x-icon name="plus" /><span class="hidden sm:inline">New</span><x-icon name="chevron-down" class="hidden sm:block !size-3.5" /></button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-56 card p-1.5 z-40">
                        @foreach ($newItems as [$label, $icon, $target])
                            @if (str_starts_with($target, 'drawer:'))
                                <button class="nav-link w-full" @click="open = false; $store.drawer.open('{{ substr($target, 7) }}')"><x-icon :name="$icon" /> {{ $label }}</button>
                            @else
                                <a href="{{ $target }}" class="nav-link"><x-icon :name="$icon" /> {{ $label }}</a>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div x-data="{ open: false }" class="relative ml-1.5" @keydown.escape="open = false">
                    <button class="flex items-center gap-1 rounded-full focus-visible:ring-2 focus-visible:ring-accent/40" @click="open = !open" :aria-expanded="open" aria-label="Account menu">
                        <x-avatar :name="$user->name" :src="$user->employee?->photoUrl()" size="size-8 text-[11.5px]" />
                        <x-icon name="chevron-down" class="hidden sm:block !size-3.5 text-muted" />
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-60 card p-1.5 z-40">
                        <div class="flex items-center gap-2.5 px-2.5 py-2 mb-1 border-b border-line-2">
                            <x-avatar :name="$user->name" :src="$user->employee?->photoUrl()" size="size-9 text-[12px]" />
                            <div class="min-w-0">
                                <div class="text-[13.5px] font-medium truncate">{{ $user->name }}</div>
                                <div class="text-[12px] text-muted truncate">{{ $user->email }}</div>
                            </div>
                        </div>
                        <a href="{{ route('account.profile') }}" class="nav-link"><x-icon name="user" /> My profile</a>
                        <a href="{{ route('account.edit') }}#profile" class="nav-link"><x-icon name="pencil" /> Edit profile</a>
                        <a href="{{ route('account.edit') }}#password" class="nav-link"><x-icon name="key-round" /> Change password</a>
                        <form method="POST" action="{{ route('logout') }}" class="mt-1 pt-1 border-t border-line-2">
                            @csrf
                            <button class="nav-link w-full text-red-fg hover:text-red-fg"><x-icon name="log-out" /> Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main @class(['flex-1 w-full', 'max-w-[1320px] mx-auto px-4 py-6 lg:px-8 lg:pt-7 pb-24 lg:pb-12' => ! ($full ?? false), 'flex flex-col pb-16 lg:pb-0' => $full ?? false])>
            {{ $slot }}
        </main>
    </div>

    {{-- Mobile bottom tabs + floating add --}}
    <nav class="no-print lg:hidden fixed bottom-0 inset-x-0 z-30 bg-surface border-t border-line grid grid-cols-5 h-16">
        @foreach (array_filter([['Dashboard', 'dashboard', 'layout-dashboard', 'dashboard'], ['Projects', 'projects.index', 'folder-kanban', 'projects.*'], $user->hasPermission('rotation.view') ? ['Rotation', 'rotation.index', 'calendar-range', 'rotation.*'] : ['Profile', 'account.profile', 'user', 'employees.*'], ['Expenses', 'expenses.index', 'wallet', 'expenses.*']]) as [$label, $route, $icon, $pattern])
            <a href="{{ route($route) }}" @class(['flex flex-col items-center justify-center gap-0.5 text-[11px]', 'text-accent' => request()->routeIs($pattern), 'text-muted' => ! request()->routeIs($pattern)])>
                <x-icon :name="$icon" class="size-5" />{{ $label }}
            </a>
        @endforeach
        <button @click="$store.sidebar.mobileOpen = true" class="flex flex-col items-center justify-center gap-0.5 text-[11px] text-muted"><x-icon name="more-horizontal" class="size-5" />More</button>
    </nav>
    <button class="no-print lg:hidden fixed right-4 bottom-20 z-30 size-14 rounded-full bg-accent text-white dark:text-gray-900 shadow-lg grid place-items-center" @click="$store.drawer.open('quick-add')">
        <x-icon name="plus" class="size-6" />
    </button>
    <x-drawer name="quick-add" title="Quick add" width="max-w-sm">
        <div class="grid grid-cols-2 gap-3">
            @foreach ($newItems as [$label, $icon, $target])
                @if (str_starts_with($target, 'drawer:'))
                    <button class="card card-pad flex flex-col items-center gap-2 text-sm" @click="$store.drawer.open('{{ substr($target, 7) }}')"><x-icon :name="$icon" class="size-5 text-accent" />{{ $label }}</button>
                @else
                    <a href="{{ $target }}" class="card card-pad flex flex-col items-center gap-2 text-sm"><x-icon :name="$icon" class="size-5 text-accent" />{{ $label }}</a>
                @endif
            @endforeach
        </div>
    </x-drawer>

    {{-- Global drawers used by the "+ New" menu --}}
    @include('partials.drawers.expense', ['projects' => $quickProjects, 'heads' => $quickHeads, 'employees' => $quickEmployees])
    @include('partials.drawers.follow-up', ['projects' => $quickProjects, 'users' => $quickUsers, 'project' => null])
    @can('quotations.manage')
        @include('partials.drawers.log-document', ['projects' => $quickProjects, 'project' => null])
    @endcan
    {{ $drawers ?? '' }}

    {{-- Toasts --}}
    <div class="no-print fixed z-[70] bottom-20 lg:bottom-6 right-4 left-4 sm:left-auto sm:w-96 space-y-2">
        <template x-for="t in $store.toasts.items" :key="t.id">
            <div x-transition class="card flex items-center gap-3 px-4 py-3 shadow-lg">
                <span :class="t.tone === 'error' ? 'bg-red-fg' : 'bg-green-fg'" class="size-2 rounded-full shrink-0"></span>
                <span class="flex-1 text-sm" x-text="t.message"></span>
                <button class="text-muted hover:text-ink text-xs" @click="$store.toasts.remove(t.id)">Dismiss</button>
            </div>
        </template>
    </div>
</div>

@if (session('success') || session('error') || $errors->any())
    <script>
        document.addEventListener('alpine:initialized', () => {
            @if (session('success')) Alpine.store('toasts').push(@js(session('success'))); @endif
            @if (session('error')) Alpine.store('toasts').push(@js(session('error')), 'error'); @endif
            @if ($errors->any()) Alpine.store('toasts').push(@js($errors->first()), 'error'); @endif
            @if ($errors->any() && old('_drawer')) Alpine.store('drawer').open(@js(old('_drawer'))); @endif
        });
    </script>
@endif
</body>
</html>
