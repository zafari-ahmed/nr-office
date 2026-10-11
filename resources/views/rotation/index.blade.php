<x-layouts.app title="Site Rotation">
    @php
        $end = $start->copy()->addDays($days - 1);
        $bar = [
            'scheduled' => 'bg-accent-soft text-accent-ink border-accent border-dashed',
            'on_site' => 'bg-blue-bg text-blue-fg border-blue-fg',
            'returning_soon' => 'bg-amber-bg text-amber-fg border-amber-fg',
            'overdue' => 'bg-red-bg text-red-fg border-red-fg',
            'returned' => 'bg-green-bg text-green-fg border-green-fg',
        ];
        $todayIdx = (int) $start->diffInDays(today(), false);
        $col = 'minmax(26px, 1fr)';
        $rangeLabel = $start->format('M') === $end->format('M') ? $start->format('j').' – '.$end->format('j M Y') : $start->format('j M').' – '.$end->format('j M Y');
    @endphp

    <x-page-header title="Site rotation" :subtitle="$onSiteCount.' on site · '.$overdue->count().' overdue · '.$returningSoon->count().' returning in '.$remindDays.' days'">
        <x-slot:actions>
            <div class="inline-flex items-center h-[34px] rounded-lg border border-line bg-surface">
                <a href="{{ route('rotation.index', ['from' => $start->copy()->subDays(7)->format('Y-m-d'), 'days' => $days]) }}" class="grid place-items-center w-8 h-full text-ink-2 hover:text-ink" title="Earlier"><x-icon name="chevron-left" class="size-4" /></a>
                <a href="{{ route('rotation.index') }}" class="px-2 text-[13px] font-medium text-ink no-underline hover:no-underline" title="Back to today">{{ $rangeLabel }}</a>
                <a href="{{ route('rotation.index', ['from' => $start->copy()->addDays(7)->format('Y-m-d'), 'days' => $days]) }}" class="grid place-items-center w-8 h-full text-ink-2 hover:text-ink" title="Later"><x-icon name="chevron-right" class="size-4" /></a>
            </div>
            <button class="btn btn-primary" @click="$store.drawer.open('visit')"><x-icon name="plus" /> Schedule visit</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_300px] gap-4 items-start">
        {{-- Timeline calendar --}}
        <div class="card overflow-hidden min-w-0">
            <div class="overflow-x-auto">
                <div class="relative min-w-[720px]" style="display: grid; grid-template-columns: 172px repeat({{ $days }}, {{ $col }});">
                    {{-- Header --}}
                    <div class="sticky left-0 z-20 bg-surface border-b border-line px-4 flex items-end pb-2.5 text-[12px] text-muted">Staff</div>
                    @for ($i = 0; $i < $days; $i++)
                        @php $d = $start->copy()->addDays($i); @endphp
                        <div @class(['border-b border-line text-center pt-2 pb-1.5 leading-tight', 'bg-accent-soft text-accent-ink' => $d->isToday(), 'bg-surface-2 text-muted' => $d->isWeekend() && ! $d->isToday(), 'text-ink-2' => ! $d->isWeekend() && ! $d->isToday()])>
                            <div class="text-[10.5px]">{{ substr($d->format('D'), 0, 2) }}</div>
                            <div @class(['text-[13.5px] font-medium', 'font-bold' => $d->isToday()])>{{ $d->format('j') }}</div>
                        </div>
                    @endfor

                    {{-- Rows --}}
                    @forelse ($staff as $emp)
                        <a href="{{ route('employees.show', [$emp, 'tab' => 'visits']) }}" class="sticky left-0 z-20 bg-surface border-b border-line-2 px-4 py-2.5 flex items-center gap-2.5 no-underline text-ink hover:no-underline" style="grid-column: 1;">
                            <x-avatar :name="$emp->full_name" :src="$emp->photoUrl()" size="size-[30px] text-[11px]" />
                            <span class="min-w-0 leading-tight"><span class="block text-[13.5px] font-medium truncate">{{ $emp->full_name }}</span><span class="block text-[12px] text-muted truncate">{{ $emp->designation }}</span></span>
                        </a>
                        <div class="relative border-b border-line-2 min-h-[53px]" style="grid-column: 2 / span {{ $days }}; display: grid; grid-template-columns: repeat({{ $days }}, {{ $col }}); grid-auto-rows: minmax(52px, auto);">
                            @for ($i = 0; $i < $days; $i++)
                                @php $d = $start->copy()->addDays($i); @endphp
                                <div @class(['border-r border-line-2', 'bg-surface-2' => $d->isWeekend() && ! $d->isToday(), 'bg-accent-soft/50' => $d->isToday()]) style="grid-row: 1 / -1; grid-column: {{ $i + 1 }};"></div>
                            @endfor
                            @foreach ($emp->siteVisits as $v)
                                @php
                                    $state = $v->displayStatus();
                                    $vEnd = $v->status === 'returned' ? $v->actual_return_date : $v->expected_return_date;
                                    $from = max(0, (int) $start->diffInDays($v->departure_date, false));
                                    $to = min($days - 1, (int) $start->diffInDays($vEnd, false));
                                    $sub = match ($state) { 'returned' => 'returned', 'overdue' => 'due '.$v->expected_return_date->format('j M'), default => '→ '.$v->expected_return_date->format('j M') };
                                @endphp
                                @if ($to >= 0 && $from < $days && $to >= $from)
                                    <div class="relative z-[1] self-center my-1.5 mx-0.5 rounded-md border px-2 py-1 leading-tight overflow-hidden {{ $bar[$state] ?? $bar['scheduled'] }}"
                                         style="grid-row: 1; grid-column: {{ $from + 1 }} / {{ $to + 2 }};"
                                         title="{{ $v->site_name }} · {{ $v->departure_date->format('j M') }} → {{ $v->expected_return_date->format('j M') }} · {{ \App\Models\SiteVisit::STATUSES[$state] }}">
                                        <div class="text-[12px] font-semibold truncate">{{ $v->site_name }}</div>
                                        <div class="text-[11px] opacity-80 truncate">{{ $sub }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @empty
                        <div style="grid-column: 1 / -1;"><x-empty icon="users" title="No site staff yet" text="Tick 'Part of the site visit rotation' on an employee's profile to show them here."><a href="{{ route('employees.index') }}" class="btn btn-secondary">Open employees</a></x-empty></div>
                    @endforelse

                    @if ($todayIdx >= 0 && $todayIdx < $days)
                        <div class="pointer-events-none absolute top-[52px] bottom-0 w-px bg-accent z-[2]" style="left: calc(172px + (100% - 172px) * {{ ($todayIdx + 0.5) / $days }});"></div>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-4 px-4 py-3 border-t border-line text-[12px] text-ink-2">
                @foreach (['scheduled' => 'Scheduled', 'on_site' => 'On site', 'returning_soon' => 'Returning soon', 'overdue' => 'Overdue', 'returned' => 'Returned'] as $k => $l)
                    <span class="inline-flex items-center gap-1.5"><span class="w-3.5 h-2.5 rounded-[3px] border {{ $bar[$k] }}"></span>{{ $l }}</span>
                @endforeach
            </div>
        </div>

        {{-- Side panels --}}
        <aside class="space-y-3.5">
            <div class="card overflow-hidden">
                <div class="card-head"><span class="size-2 rounded-full bg-red-fg"></span><h2 class="card-title">Overdue</h2></div>
                @forelse ($overdue as $v)
                    @php $late = (int) $v->expected_return_date->diffInDays(today()); @endphp
                    <div class="px-4 py-3 border-b border-line-2 last:border-b-0">
                        <div class="flex items-center gap-2.5">
                            <x-avatar :name="$v->employee->full_name" :src="$v->employee->photoUrl()" size="size-8 text-[11px]" />
                            <div class="flex-1 min-w-0 leading-tight"><div class="text-[13.5px] font-medium truncate">{{ $v->employee->full_name }}</div><div class="text-[12px] text-muted truncate">{{ $v->site_name }}</div></div>
                            <span class="text-[12.5px] font-semibold text-red-fg whitespace-nowrap">{{ $late }} {{ str('day')->plural($late) }} overdue</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2.5">
                            <x-action :url="route('rotation.return', $v)" block class="btn btn-secondary btn-sm">Mark returned</x-action>
                            <x-action :url="route('rotation.extend', $v)" data-days="2" block class="btn btn-secondary btn-sm">Extend 2 days</x-action>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-3.5 text-[13px] text-muted">Nobody is overdue.</p>
                @endforelse
            </div>
            <div class="card overflow-hidden">
                <div class="card-head"><span class="size-2 rounded-full bg-amber-fg"></span><h2 class="card-title">Returning soon</h2><span class="text-[12.5px] text-muted">next {{ $remindDays }} days</span></div>
                @forelse ($returningSoon as $v)
                    @php
                        $total = max(1, (int) $v->departure_date->diffInDays($v->expected_return_date) + 1);
                        $dayN = min($total, (int) $v->departure_date->diffInDays(today()) + 1);
                        $due = $v->expected_return_date;
                    @endphp
                    <div class="px-4 py-3 border-b border-line-2 last:border-b-0">
                        <div class="flex items-center gap-2.5">
                            <x-avatar :name="$v->employee->full_name" :src="$v->employee->photoUrl()" size="size-8 text-[11px]" />
                            <div class="flex-1 min-w-0 leading-tight"><div class="text-[13.5px] font-medium truncate">{{ $v->employee->full_name }}</div><div class="text-[12px] text-muted truncate">{{ $v->site_name }}</div></div>
                            <span class="text-[12.5px] font-semibold text-amber-fg whitespace-nowrap">{{ $due->isToday() ? 'Today' : ($due->isTomorrow() ? 'Tomorrow' : $due->format('j M')) }}</span>
                        </div>
                        <div class="bar mt-2.5"><span class="{{ $dayN / $total > .85 ? 'bg-amber-fg' : 'bg-blue-fg' }}" style="width: {{ round($dayN / $total * 100) }}%"></span></div>
                        <div class="flex items-center justify-between mt-1.5 text-[12px] text-muted">
                            <span>Day {{ $dayN }} of {{ $total }}</span>
                            <x-action :url="route('rotation.return', $v)" class="text-[12px] font-medium text-accent hover:underline cursor-pointer">Mark returned</x-action>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-3.5 text-[13px] text-muted">No returns in the next {{ $remindDays }} days.</p>
                @endforelse
            </div>
            <div class="card overflow-hidden">
                <div class="card-head"><span class="size-2 rounded-full bg-accent"></span><h2 class="card-title">Scheduled</h2></div>
                @forelse ($upcoming as $v)
                    <div class="flex items-center gap-2.5 px-4 py-2.5 border-b border-line-2 last:border-b-0">
                        <x-avatar :name="$v->employee->full_name" size="size-7 text-[10px]" />
                        <div class="flex-1 min-w-0 leading-tight"><div class="text-[13.5px] font-medium truncate">{{ $v->employee->full_name }}</div><div class="text-[12px] text-muted truncate">{{ $v->site_name }} · leaves {{ $v->departure_date->format('j M') }}</div></div>
                        <x-action :url="route('rotation.depart', $v)" class="btn btn-secondary btn-xs">Departed</x-action>
                        <x-action :url="route('rotation.cancel', $v)" confirm="Cancel this visit?" class="btn btn-icon !size-7 !w-7" title="Cancel visit"><x-icon name="x" class="!size-3.5" /></x-action>
                    </div>
                @empty
                    <p class="px-4 py-3.5 text-[13px] text-muted">Nothing scheduled.</p>
                @endforelse
            </div>
        </aside>
    </div>

    <x-slot:drawers>
        @if (request()->boolean('schedule'))
        <script>document.addEventListener('alpine:initialized', () => Alpine.store('drawer').open('visit'));</script>
        @endif
        <x-drawer name="visit" title="Schedule site visit" subtitle="Return date is calculated from the stay length">
            <form id="visit-form" method="POST" action="{{ route('rotation.store') }}" class="space-y-4"
                  x-data="visitForm({ departure: '{{ old('departure_date', today()->format('Y-m-d')) }}', days: {{ (int) old('planned_days', $defaultDays) }}, countWeekends: {{ \App\Models\Setting::get('rotation.count_weekends', '1') === '1' ? 'true' : 'false' }} })">
                @csrf
                <input type="hidden" name="_drawer" value="visit">
                @if ($suggested)
                    <div class="rounded-lg bg-accent-soft text-accent px-3 py-2.5 text-[13px] flex items-center gap-2">
                        <x-icon name="sparkles" /> <span>Next in rotation: <strong>{{ $suggested->full_name }}</strong>{{ $suggested->last_return ? ', back since '.\Illuminate\Support\Carbon::parse($suggested->last_return)->format('d M') : ', no visits yet' }}</span>
                    </div>
                @endif
                <x-field label="Staff member" name="employee_id" :options="$employees->pluck('full_name', 'id')" :value="$suggested?->id" required />
                <x-field label="Project" name="project_id" :options="$projects->pluck('name', 'id')" blank="Not linked to a project" />
                <x-field label="Site name" name="site_name" placeholder="Defaults to the project name" />
                <x-field label="Site address" name="site_address" />
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="label">Departure <span class="text-red-fg">*</span></label><input type="date" name="departure_date" x-model="departure" class="input" required></div>
                    <div><label class="label">Days on site <span class="text-red-fg">*</span></label><input type="number" name="planned_days" x-model="days" min="1" max="120" class="input" required></div>
                </div>
                <div class="rounded-lg border border-line px-3 py-2.5 text-[13px] flex items-center justify-between">
                    <span class="text-muted">Expected return</span><span class="font-semibold" x-text="returnDate"></span>
                </div>
                <x-field label="Purpose" name="purpose" placeholder="Supervision, measurement, handover…" />
                <x-field label="Notes" name="notes" type="textarea" rows="2" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
                <button form="visit-form" class="btn btn-primary"><x-icon name="check" /> Schedule</button>
            </x-slot:footer>
        </x-drawer>
    </x-slot:drawers>
</x-layouts.app>
