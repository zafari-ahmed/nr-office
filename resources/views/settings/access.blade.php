<x-layouts.app title="Access rights">
    <x-page-header title="Settings" subtitle="Access rights" />
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6 max-w-[1120px]">
        @include('settings._nav')
        <form method="POST" action="{{ route('settings.access.update') }}" class="card overflow-hidden min-w-0">
            @csrf @method('PUT')
            <div class="px-5 py-4 border-b border-line">
                <h2 class="card-title">Access rights</h2>
                <p class="text-[13px] text-ink-2 mt-0.5">Choose what each role can see and do. Owner / Admin always has full access. Everyone can open their own profile, add their own expenses and see projects they manage or are on the team of.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-full">Permission</th>
                            <th class="text-center whitespace-nowrap">Owner / Admin</th>
                            @foreach ($roles as $key => $label)
                                <th class="text-center whitespace-nowrap">{{ $label }}<div class="text-[11px] font-normal text-muted">{{ $counts[$key] ?? 0 }} {{ str('user')->plural($counts[$key] ?? 0) }}</div></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Support\Access::GROUPS as $group => $permissions)
                            <tr><td colspan="{{ count($roles) + 2 }}" class="!py-2 bg-surface-2 text-[12px] font-semibold uppercase tracking-[.04em] text-muted">{{ $group }}</td></tr>
                            @foreach ($permissions as $permission => [$title, $hint])
                                <tr>
                                    <td>
                                        <div class="text-[13.5px] font-medium">{{ $title }}</div>
                                        <div class="text-[12px] text-muted">{{ $hint }}</div>
                                    </td>
                                    <td class="text-center"><span class="switch opacity-50 cursor-not-allowed" title="Admin always has full access"><input type="checkbox" checked disabled><span></span></span></td>
                                    @foreach ($roles as $role => $label)
                                        <td class="text-center">
                                            <label class="switch" title="{{ $label }}: {{ $title }}">
                                                <input type="checkbox" name="access[{{ $role }}][]" value="{{ $permission }}" @checked(in_array($permission, $granted[$role], true)) aria-label="{{ $label }}: {{ $title }}"><span></span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 bg-surface-2 border-t border-line">
                <button name="reset" value="1" class="btn btn-ghost btn-sm" onclick="return confirm('Reset every role to the default access rights?')"><x-icon name="rotate-ccw" /> Reset to defaults</button>
                <button class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</x-layouts.app>
