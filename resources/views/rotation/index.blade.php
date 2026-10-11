<x-layouts.app title="Site Rotation">
    @php
        $end = $start->copy()->addDays($days - 1);
        $barTone = [
            'scheduled' => 'bg-gray-200 text-gray-700 dark:bg-gray-600/40 dark:text-gray-200',
            'on_site' => 'bg-blue-500 text-white',
            'returning_soon' => 'bg-amber-400 text-amber-950',
            'overdue' => 'bg-red-500 text-white',
            'returned' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
        ];
    @endphp

    <x-page-header title="Site Rotation" :subtitle="'Default stay '.$defaultDays.' days · reminder '.$remindDays.' days before return'">
        <x-slot:actions>
            <div class="flex items-center gap-1">
                <a href="{{ route('rotation.index', ['from' => $start->copy()->subDays(14)->format('Y-m-d'), 'days' => $days]) }}" class="btn btn-icon"><x-icon name="chevron-left" /></a>
                <a href="{{ route('rotation.index') }}" class="btn btn-secondary btn-sm">Today</a>
                <a href="{{ route('rotation.index', ['from' => $start->copy()->addDays(14)->format('Y-m-d'), 'days' => $days]) }}" class="btn btn-icon"><x-icon name="chevron-right" /></a>
            </div>
            <button class="btn btn-primary" @click="$store.drawer.open('visit')"><x-icon name="plus" /> Schedule visit</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid xl:grid-cols-[1fr_300px] gap-6">
        {{-- Timeline calendar --}}
        <div class="card overflow-hidden min-w-0">
            <div class="flex flex-wrap gap-3 px-5 py-3 border-b border-line text-xs text-muted">
                @foreach (['scheduled' => 'Scheduled', 'on_site' => 'On site', 'returning_soon' => 'Returning soon', 'overdue' => 'Overdue', 'returned' => 'Returned'] as $k => $l)
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm {{ explode(' ', $barTone[$k])[0] }}"></span>{{ $l }}</span>
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <div class="min-w-[900px]" style="display: grid; grid-template-columns: 180px repeat({{ $days }}, minmax(28px, 1fr));">
                    {{-- Header --}}
                    <div class="sticky left-0 z-10 bg-surface border-b border-line px-4 py-2 text-xs text-muted">{{ $start->format('M Y') }}</div>
                    @for ($i = 0; $i < $days; $i++)
                        @php $d = $start->copy()->addDays($i); @endphp
                        <div @class(['border-b border-line text-center py-1.5 text-[10px] leading-tight', 'bg-accent-soft text-accent font-semibold' => $d->isToday(), 'text-muted' => ! $d->isToday(), 'bg-surface-2/60' => $d->isFriday() && ! $d->isToday()])>
                            <div>{{ $d->format('D')[0] }}</div><div class="text-[11px]">{{ $d->format('j') }}</div>
                        </div>
                    @endfor

                    {{-- Rows --}}
                    @forelse ($staff as $emp)
                        <a href="{{ route('employees.show', [$emp, 'tab' => 'visits']) }}" class="sticky left-0 z-10 bg-surface border-b border-line px-4 py-2.5 flex items-center gap-2 hover:text-accent" style="grid-column: 1;">
                            <x-avatar :name="$emp->full_name" :src="$emp->photoUrl()" size="size-7 text-[10px]" />
                            <span class="text-[13px] font-medium truncate">{{ $emp->full_name }}</span>
                        </a>
                        <div class="relative border-b border-line" style="grid-column: 2 / span {{ $days }}; display: grid; grid-template-columns: repeat({{ $days }}, minmax(28px, 1fr));">
                            @php $todayIdx = $start->diffInDays(today(), false); @endphp
                            @if ($todayIdx >= 0 && $todayIdx < $days)
                                <div class="absolute inset-y-0 bg-accent-soft/60" style="left: calc({{ $todayIdx }} / {{ $days }} * 100%); width: calc(100% / {{ $days }});"></div>
                            @endif
                            @foreach ($emp->siteVisits as $v)
                                @php
                                    $state = $v->displayStatus();
                                    $vEnd = $v->status === 'returned' ? $v->actual_return_date : ($state === 'overdue' ? today() : $v->expected_return_date);
                                    $from = max(0, (int) $start->diffInDays($v->departure_date, false));
                                    $to = min($days - 1, (int) $start->diffInDays($vEnd, false));
                                @endphp
                                @if ($to >= 0 && $from < $days && $to >= $from)
                                    <div class="relative z-[1] my-1.5 mx-0.5 h-7 rounded-md px-2 text-[11px] font-medium flex items-center truncate {{ $barTone[$state] ?? $barTone['scheduled'] }}"
                                         style="grid-row: 1; grid-column: {{ $from + 1 }} / {{ $to + 2 }};"
                                         title="{{ $v->site_name }} · {{ $v->departure_date->format('d M') }} → {{ $v->expected_return_date->format('d M') }} · {{ \App\Models\SiteVisit::STATUSES[$state] }}">
                                        {{ $v->site_name }}
                                    </div>
                                @endif
                            @endforeach
                            @if ($emp->siteVisits->isEmpty())<div style="grid-row: 1; grid-column: 1;" class="h-10"></div>@endif
                        </div>
                    @empty
                        <div style="grid-column: 1 / -1;"><x-empty icon="users" title="No site staff yet" text="Tick 'Part of the site visit rotation' on an employee's profile to show them here."><a href="{{ route('employees.index') }}" class="btn btn-secondary">Open employees</a></x-empty></div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Side panels --}}
        <aside class="space-y-4">
            <div class="card">
                <div class="flex items-center justify-between px-5 pt-5 pb-2"><h2 class="card-title">Overdue</h2><span class="pill pill-red">{{ $overdue->count() }}</span></div>
                @forelse ($overdue as $v)
                    @include('rotation._row', ['v' => $v])
                @empty
                    <p class="px-5 pb-5 text-[13px] text-muted">Nobody is overdue.</p>
                @endforelse
            </div>
            <div class="card">
                <div class="flex items-center justify-between px-5 pt-5 pb-2"><h2 class="card-title">Returning soon</h2><span class="pill pill-amber">{{ $returningSoon->count() }}</span></div>
                @forelse ($returningSoon as $v)
                    @include('rotation._row', ['v' => $v])
                @empty
                    <p class="px-5 pb-5 text-[13px] text-muted">No returns in the next {{ $remindDays }} days.</p>
                @endforelse
            </div>
            <div class="card">
                <div class="px-5 pt-5 pb-2"><h2 class="card-title">Scheduled</h2></div>
                @forelse ($upcoming as $v)
                    <div class="flex items-center gap-3 px-5 py-2.5 border-t border-line">
                        <div class="flex-1 min-w-0 text-[13px]"><div class="font-medium truncate">{{ $v->employee->full_name }}</div><div class="text-xs text-muted truncate">{{ $v->site_name }} · leaves {{ $v->departure_date->format('d M') }}</div></div>
                        <x-action :url="route('rotation.depart', $v)" class="btn btn-secondary btn-xs">Departed</x-action>
                        <x-action :url="route('rotation.cancel', $v)" confirm="Cancel this visit?" class="btn btn-ghost btn-xs" title="Cancel">&times;</x-action>
                    </div>
                @empty
                    <p class="px-5 pb-5 text-[13px] text-muted">Nothing scheduled.</p>
                @endforelse
            </div>
        </aside>
    </div>

    <x-slot:drawers>
        <x-drawer name="visit" title="Schedule site visit" subtitle="Return date is calculated from the stay length">
            <form id="visit-form" method="POST" action="{{ route('rotation.store') }}" class="space-y-4"
                  x-data="visitForm({ departure: '{{ old('departure_date', today()->format('Y-m-d')) }}', days: {{ (int) old('planned_days', $defaultDays) }} })">
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
                    <div><label class="label">Departure <span class="text-red-500">*</span></label><input type="date" name="departure_date" x-model="departure" class="input" required></div>
                    <div><label class="label">Days on site <span class="text-red-500">*</span></label><input type="number" name="planned_days" x-model="days" min="1" max="120" class="input" required></div>
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
