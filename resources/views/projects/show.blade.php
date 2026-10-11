<x-layouts.app :title="$project->name">
    @php
        $tabs = [
            'overview' => ['Overview', null],
            'follow-ups' => ['Follow-ups', $project->followUps->where('status', 'pending')->count()],
            'quotations' => ['Quotations', $project->quotations->count()],
            'invoices' => ['Invoices', $project->invoices->count()],
            'expenses' => ['Expenses', null],
            'site-visits' => ['Site visits', null],
            'files' => ['Files', null],
            'notes' => ['Notes', null],
        ];
        $pct = fn ($a, $b) => $b > 0 ? round($a / $b * 100).'%' : '0%';
        $latestQuote = $project->quotations->whereNotIn('status', ['revised', 'rejected'])->sortByDesc('id')->first();
        $openInvoices = $project->invoices->whereNotIn('status', ['paid', 'cancelled', 'draft'])->count();
        $kpiCells = [
            ['Quoted', $kpis['quoted'], $latestQuote ? $latestQuote->label() : 'No quotation yet', ''],
            ['Invoiced', $kpis['invoiced'], $pct($kpis['invoiced'], $kpis['quoted']).' of quoted', ''],
            ['Received', $kpis['received'], $pct($kpis['received'], $kpis['invoiced']).' of invoiced', $kpis['received'] > 0 ? 'text-green-fg' : ''],
            ['Outstanding', $kpis['outstanding'], $openInvoices.' open '.str('invoice')->plural($openInvoices), $kpis['outstanding'] > 0 ? 'text-amber-fg' : ''],
            ['Expenses', $kpis['expenses'], $pct($kpis['expenses'], $kpis['quoted']).' of quoted', ''],
        ];
        $team = collect([$project->manager ? ['name' => $project->manager->name, 'role' => 'Project Manager'] : null])
            ->merge($project->siteVisits->toBase()->pluck('employee')->filter()->unique(fn ($e) => $e->id)->map(fn ($e) => ['name' => $e->full_name, 'role' => $e->designation]))
            ->filter()->take(6);
        $next = $project->followUps->where('status', 'pending')->sortBy('due_date')->first();
    @endphp

    {{-- Header --}}
    <x-page-header :title="$project->name">
        <x-slot:crumbs>
            <a href="{{ route('projects.index') }}" class="text-muted hover:text-ink no-underline">Projects</a>
            <x-icon name="chevron-right" class="size-3.5" />
            <span class="font-mono text-[12px]">{{ $project->code }}</span>
        </x-slot:crumbs>
        <x-slot:badges>
            <x-pill :status="$project->status" :label="\App\Models\Project::STATUSES[$project->status]" />
            <span class="code-chip">{{ $project->code }}</span>
        </x-slot:badges>
        <x-slot:meta>
            @if ($project->client)<span class="inline-flex items-center gap-1.5"><x-icon name="building-2" class="size-3.5" />{{ $project->client->displayName() }}</span>@endif
            @if ($project->site_address || $project->city)<span class="inline-flex items-center gap-1.5"><x-icon name="map-pin" class="size-3.5" />{{ collect([$project->site_address, $project->city])->filter()->implode(', ') }}</span>@endif
            @if ($project->manager)<span class="inline-flex items-center gap-1.5"><x-icon name="user" class="size-3.5" />{{ $project->manager->name }}</span>@endif
        </x-slot:meta>
        <x-slot:actions>
            <form method="POST" action="{{ route('projects.status', $project) }}" x-data>
                @csrf @method('PATCH')
                <select name="status" class="input !h-[34px] !w-32" @change="$el.form.submit()" title="Change status">
                    @foreach (\App\Models\Project::STATUSES as $k => $l)<option value="{{ $k }}" @selected($project->status === $k)>{{ $l }}</option>@endforeach
                </select>
            </form>
            <a href="{{ route('projects.edit', $project) }}" class="btn btn-icon btn-secondary" title="Edit project"><x-icon name="pencil" /></a>
            <button class="btn btn-secondary" @click="$store.drawer.open('log-document-{{ $project->id }}')"><x-icon name="paperclip" /> Log sent document</button>
            <a href="{{ route('quotations.create', ['project' => $project->id]) }}" class="btn btn-primary"><x-icon name="file-plus" /> New quotation</a>
        </x-slot:actions>
    </x-page-header>

    {{-- KPI strip --}}
    <div class="card grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 mb-5 overflow-hidden">
        @foreach ($kpiCells as [$label, $amount, $sub, $tone])
            <div class="px-[18px] py-4 border-line border-b lg:border-b-0 [&:not(:last-child)]:border-r">
                <div class="text-[13px] text-ink-2">{{ $label }}</div>
                <div @class(['text-[22px] font-semibold tracking-[-.01em] mt-1.5 tabular-nums', $tone])>{{ money($amount) }}</div>
                <div class="text-[12px] text-muted mt-0.5 truncate">{{ $sub }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">
        <div class="min-w-0">
            <nav class="tabs mb-5">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('projects.show', [$project, 'tab' => $key]) }}" @class(['tab', 'tab-active' => $tab === $key])>{{ $label[0] }}@if ($label[1] !== null)<span class="count bg-bg2 text-muted !h-[18px] !text-[11px] font-mono font-medium">{{ $label[1] }}</span>@endif</a>
                @endforeach
            </nav>

            @switch($tab)
                @case('follow-ups')
                    <div class="card">
                        @forelse ($project->followUps as $f)
                            <div class="flex items-center gap-3 px-5 py-3 border-b border-line last:border-0">
                                <span @class(['size-2.5 rounded-full shrink-0', 'bg-green-fg' => $f->status === 'done', 'bg-red-fg' => $f->isOverdue(), 'bg-amber-fg' => $f->status === 'pending' && ! $f->isOverdue()])></span>
                                <div class="min-w-0 flex-1">
                                    <div @class(['font-medium', 'line-through text-muted' => $f->status === 'done'])>{{ $f->title }}</div>
                                    <div class="text-xs text-muted">{{ $f->due_date->format('D, d M Y') }} · {{ $f->assignee?->name ?? 'Unassigned' }}@if ($f->notes) · {{ \Illuminate\Support\Str::limit($f->notes, 80) }}@endif</div>
                                </div>
                                @if ($f->status === 'pending')
                                    <x-action :url="route('follow-ups.snooze', $f)" data-days="2" class="btn btn-ghost btn-xs"><x-icon name="alarm-clock" /> Snooze</x-action>
                                    <x-action :url="route('follow-ups.done', $f)" class="btn btn-secondary btn-xs"><x-icon name="check" /> Done</x-action>
                                @else
                                    <span class="text-xs text-muted">Done {{ $f->completed_at?->format('d M') }}</span>
                                @endif
                            </div>
                        @empty
                            <x-empty icon="calendar-clock" title="No follow-ups yet"><button class="btn btn-primary" @click="$store.drawer.open('follow-up-{{ $project->id }}')">Add follow-up</button></x-empty>
                        @endforelse
                    </div>
                    @break

                @case('quotations')
                    <div class="flex justify-end mb-3"><a href="{{ route('quotations.create', ['project' => $project->id]) }}" class="btn btn-primary btn-sm"><x-icon name="plus" /> New quotation</a></div>
                    <div class="card overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Number</th><th>Title</th><th>Date</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                            <tbody>
                            @forelse ($project->quotations as $q)
                                <tr class="cursor-pointer" onclick="location='{{ route('quotations.show', $q) }}'">
                                    <td class="font-medium">{{ $q->quotation_number }} <span class="text-xs text-muted">Rev {{ $q->revision }}</span></td>
                                    <td>{{ $q->title }}</td><td>{{ $q->issue_date->format('d M Y') }}</td>
                                    <td><x-pill :status="$q->status" /></td><td class="text-right tabular-nums">{{ money($q->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty icon="file-text" title="No quotations yet" /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @break

                @case('invoices')
                    <div class="flex justify-end mb-3"><a href="{{ route('invoices.create', ['project' => $project->id]) }}" class="btn btn-primary btn-sm"><x-icon name="plus" /> New invoice</a></div>
                    <div class="card overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Number</th><th>Issued</th><th>Due</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
                            <tbody>
                            @forelse ($project->invoices as $i)
                                <tr class="cursor-pointer" onclick="location='{{ route('invoices.show', $i) }}'">
                                    <td class="font-medium">{{ $i->invoice_number }}</td><td>{{ $i->issue_date->format('d M Y') }}</td><td>{{ $i->due_date?->format('d M Y') }}</td>
                                    <td><x-pill :status="$i->status" /></td><td class="text-right tabular-nums">{{ money($i->total) }}</td><td class="text-right tabular-nums">{{ money($i->balance_due) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty icon="receipt" title="No invoices yet" /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @break

                @case('expenses')
                    <div class="flex justify-end mb-3"><button class="btn btn-primary btn-sm" @click="$store.drawer.open('expense')"><x-icon name="plus" /> Add expense</button></div>
                    <div class="card overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Head</th><th>Description</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                            @forelse ($project->expenses as $e)
                                <tr><td>{{ $e->expense_date->format('d M Y') }}</td><td>{{ $e->head->name }}</td><td>{{ $e->description }}</td><td><x-pill :status="$e->status" /></td><td class="text-right tabular-nums">{{ money($e->amount) }}</td></tr>
                            @empty
                                <tr><td colspan="5"><x-empty icon="wallet" title="No expenses linked to this project" /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @break

                @case('site-visits')
                    <div class="card overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Left</th><th>Return</th><th>Status</th><th>Purpose</th></tr></thead>
                            <tbody>
                            @forelse ($project->siteVisits as $v)
                                <tr>
                                    <td class="font-medium">{{ $v->employee->full_name }}</td><td>{{ $v->departure_date->format('d M') }}</td>
                                    <td>{{ ($v->actual_return_date ?? $v->expected_return_date)->format('d M') }}</td>
                                    <td><x-pill :status="$v->displayStatus()" /></td><td class="text-muted">{{ $v->purpose }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty icon="map-pin" title="No site visits yet" /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @break

                @case('files')
                    <div class="card card-pad space-y-4">
                        <form method="POST" action="{{ route('projects.files', $project) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <x-dropzone label="Upload drawings, BOQs, photos or contracts" />
                            <div class="flex justify-end"><button class="btn btn-primary btn-sm"><x-icon name="upload" /> Upload</button></div>
                        </form>
                        @if ($project->attachments->isNotEmpty())
                            <x-attachment-list :attachments="$project->attachments" />
                        @endif
                        @foreach ($project->documentLogs->filter(fn ($d) => $d->attachments->isNotEmpty()) as $log)
                            <div>
                                <div class="section-title mb-1">From {{ strtolower(\App\Models\DocumentLog::TYPES[$log->document_type]) }} log {{ $log->reference_no }}</div>
                                <x-attachment-list :attachments="$log->attachments" :deletable="false" />
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('notes')
                    <div class="card card-pad mb-4">
                        <form method="POST" action="{{ route('notes.store', $project) }}" class="space-y-3">
                            @csrf
                            <x-field name="body" type="textarea" rows="3" placeholder="Write a note for the team…" />
                            <div class="flex justify-end"><button class="btn btn-primary btn-sm">Add note</button></div>
                        </form>
                    </div>
                    <div class="space-y-3">
                        @foreach ($project->notes as $note)
                            <div class="card card-pad">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2 text-[13px]"><x-avatar :name="$note->user?->name ?? '?'" size="size-6 text-[10px]" /> <span class="font-medium">{{ $note->user?->name }}</span> <span class="text-muted">{{ $note->created_at->diffForHumans() }}</span></div>
                                    <div class="flex">
                                        <x-action :url="route('notes.pin', $note)" @class(['btn btn-icon', 'text-accent' => $note->is_pinned]) title="Pin"><x-icon name="pin" /></x-action>
                                        <x-action :url="route('notes.destroy', $note)" method="DELETE" confirm="Delete this note?" class="btn btn-icon hover:text-red-fg"><x-icon name="trash-2" /></x-action>
                                    </div>
                                </div>
                                <p class="whitespace-pre-line">{{ $note->body }}</p>
                            </div>
                        @endforeach
                    </div>
                    @break

                @default
                    @if ($project->description)
                        <div class="card px-[18px] py-3.5 mb-4 text-[13.5px] text-ink-2 whitespace-pre-line"><span class="section-title block mb-1">Scope</span>{{ $project->description }}</div>
                    @endif
                    <ol class="relative">
                        <span class="absolute left-[15px] top-2 bottom-2 w-px bg-line"></span>
                        @forelse ($timeline as $entry)
                            @if ($entry['kind'] === 'log')
                                @php $log = $entry['item']; @endphp
                                <li class="relative flex gap-3.5 pb-4">
                                    <span class="relative z-10 grid place-items-center size-8 rounded-full bg-accent-soft text-accent-ink ring-4 ring-bg shrink-0"><x-icon name="paperclip" class="size-4" /></span>
                                    <div class="card flex-1 min-w-0 p-4" x-data="{ open: false }">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="tag bg-grey-bg text-grey-fg"><x-icon name="paperclip" class="size-3" /> Logged</span>
                                            <span class="tag bg-accent-soft text-accent-ink normal-case tracking-normal text-[12px] font-medium rounded-full">{{ \App\Models\DocumentLog::TYPES[$log->document_type] }}</span>
                                            <span class="font-mono text-[13px] font-semibold">{{ $log->reference_no }}</span>
                                            @if ($log->amount)<span class="font-semibold tabular-nums">{{ money($log->amount) }}</span>@endif
                                            <x-pill :status="$log->status" class="ml-auto" />
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5 text-[12.5px] text-ink-2 mt-2.5">
                                            <x-avatar :name="$log->creator?->name ?? '?'" size="size-5 text-[9px]" />
                                            <span class="font-medium text-ink">{{ $log->creator?->name ?? '—' }}</span>
                                            <x-icon name="arrow-right" class="size-3.5 text-muted" />
                                            <span>{{ $log->sent_to_name ?: '—' }}</span>
                                            @if ($log->sent_to_email)<span class="text-muted">&lt;{{ $log->sent_to_email }}&gt;</span>@endif
                                            <span class="text-muted">· {{ $log->sent_date->format('j M') }}</span>
                                            @if ($log->attachments->isNotEmpty())<span class="text-muted inline-flex items-center gap-0.5"><x-icon name="paperclip" class="size-3" />{{ $log->attachments->count() }}</span>@endif
                                        </div>
                                        @if ($log->email_subject || $log->email_body)
                                            <div class="mt-3 rounded-lg bg-surface-2 px-3.5 py-3">
                                                @if ($log->email_subject)<div class="text-[13.5px] font-semibold">{{ $log->email_subject }}</div>@endif
                                                <div class="text-[13px] text-muted mt-1 whitespace-pre-line" x-show="!open">{{ \Illuminate\Support\Str::limit($log->email_body, 180) }}</div>
                                                <div class="text-[13px] text-ink-2 mt-1 whitespace-pre-line" x-show="open" x-cloak>{{ $log->email_body }}</div>
                                                @if (strlen((string) $log->email_body) > 180)
                                                    <button class="mt-1.5 text-[12.5px] font-medium text-accent inline-flex items-center gap-1" @click="open = !open"><x-icon name="mail" class="size-3.5" /><span x-text="open ? 'Hide full email' : 'Show full email'"></span></button>
                                                @endif
                                            </div>
                                        @endif
                                        @if ($log->last_discussion)
                                            <div class="mt-2.5 rounded-lg bg-amber-bg px-3.5 py-2.5">
                                                <div class="text-[11px] font-semibold uppercase tracking-[.04em] text-amber-fg">What was discussed</div>
                                                <div class="text-[13.5px] text-ink mt-1">{{ $log->last_discussion }}</div>
                                            </div>
                                        @endif
                                        @if ($log->attachments->isNotEmpty())
                                            <div class="flex flex-wrap gap-2 mt-3">
                                                @foreach ($log->attachments as $file)
                                                    <a href="{{ route('attachments.download', [$file, 'inline' => 1]) }}" target="_blank" class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-md border border-line text-[12.5px] text-ink-2 no-underline hover:text-ink hover:no-underline"><x-icon name="file" class="size-3.5" />{{ \Illuminate\Support\Str::limit($file->original_name, 34) }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @else
                                @php $a = $entry['item']; [$icon, $tone] = activity_style($a->action); @endphp
                                <li class="relative flex gap-3.5 pb-4">
                                    <span class="relative z-10 grid place-items-center size-8 rounded-full ring-4 ring-bg shrink-0 {{ $tone === 'teal' ? 'bg-accent-soft text-accent-ink' : "bg-$tone-bg text-$tone-fg" }}"><x-icon :name="$icon" class="size-4" /></span>
                                    <div class="flex-1 min-w-0 pt-1">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-1 text-[13.5px] font-medium">{{ $a->description }}</div>
                                            <span class="text-[12px] text-muted shrink-0">{{ $a->created_at->format('j M') }}</span>
                                        </div>
                                        <div class="text-[12.5px] text-muted">{{ $a->user?->name ?? 'System' }}</div>
                                    </div>
                                </li>
                            @endif
                        @empty
                            <li class="pl-12 text-muted">No activity yet.</li>
                        @endforelse
                    </ol>
            @endswitch
        </div>

        {{-- Right rail --}}
        <aside class="space-y-3.5">
            <div class="card p-4">
                <div class="section-title mb-3">Next follow-up</div>
                @if ($next)
                    <div class="flex gap-3">
                        <div @class(['grid place-items-center w-12 h-[52px] rounded-lg shrink-0 text-center leading-none', 'bg-red-bg text-red-fg' => $next->isOverdue(), 'bg-amber-bg text-amber-fg' => ! $next->isOverdue()])>
                            <div><div class="text-[11.5px] font-medium">{{ $next->due_date->format('D') }}</div><div class="font-mono text-[20px] font-semibold mt-0.5">{{ $next->due_date->format('j') }}</div></div>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[14px] font-medium leading-snug">{{ $next->title }}</div>
                            <div class="text-[12.5px] text-muted mt-0.5">Assigned to {{ $next->assignee ? strtok($next->assignee->name, ' ') : 'nobody' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-3.5">
                        <x-action :url="route('follow-ups.done', $next)" block class="btn btn-secondary btn-sm"><x-icon name="check" /> Done</x-action>
                        <x-action :url="route('follow-ups.snooze', $next)" data-days="2" block class="btn btn-secondary btn-sm"><x-icon name="clock" /> Snooze</x-action>
                    </div>
                @else
                    <p class="text-muted text-[13px]">Nothing scheduled.</p>
                @endif
                <button class="btn w-full mt-2.5 bg-accent-soft text-accent-ink hover:brightness-95" @click="$store.drawer.open('follow-up-{{ $project->id }}')"><x-icon name="plus" /> Add follow-up</button>
            </div>
            @if ($project->client)
                <div class="card p-4 text-[13.5px]">
                    <div class="section-title mb-2.5">Client contact</div>
                    <div class="font-medium">{{ $project->client->contact_person ?: $project->client->name }}</div>
                    @if ($project->client->email)<a href="mailto:{{ $project->client->email }}" class="flex items-center gap-2 mt-1.5 text-ink-2 no-underline hover:text-ink"><x-icon name="mail" class="size-3.5 text-muted" />{{ $project->client->email }}</a>@endif
                    @if ($project->client->phone)<a href="tel:{{ $project->client->phone }}" class="flex items-center gap-2 mt-1 text-ink-2 no-underline hover:text-ink"><x-icon name="phone" class="size-3.5 text-muted" />{{ $project->client->phone }}</a>@endif
                </div>
            @endif
            @if ($team->isNotEmpty())
                <div class="card p-4">
                    <div class="section-title mb-2.5">Team</div>
                    <div class="space-y-2.5">
                        @foreach ($team as $m)
                            <div class="flex items-center gap-2.5">
                                <x-avatar :name="$m['name']" size="size-[30px] text-[11px]" />
                                <div class="leading-tight"><div class="text-[13.5px] font-medium">{{ $m['name'] }}</div><div class="text-[12px] text-muted">{{ $m['role'] }}</div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="card p-4 text-[13px] space-y-1.5">
                <div class="section-title mb-2">Details</div>
                <div class="flex justify-between"><span class="text-muted">Project value</span><span class="font-medium tabular-nums">{{ money($project->project_value) }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Start</span><span>{{ $project->start_date?->format('j M Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">End</span><span>{{ $project->end_date?->format('j M Y') ?? '—' }}</span></div>
            </div>
        </aside>
    </div>

    <x-slot:drawers>
        @include('partials.drawers.follow-up', ['project' => $project, 'users' => $users, 'projects' => collect()])
        @include('partials.drawers.log-document', ['project' => $project, 'projects' => collect([$project])])
    </x-slot:drawers>
</x-layouts.app>
