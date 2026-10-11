<x-layouts.app :title="$project->name">
    @php
        $tabs = [
            'overview' => 'Overview',
            'follow-ups' => 'Follow-ups ('.$project->followUps->where('status', 'pending')->count().')',
            'quotations' => 'Quotations ('.$project->quotations->count().')',
            'invoices' => 'Invoices ('.$project->invoices->count().')',
            'expenses' => 'Expenses',
            'site-visits' => 'Site visits',
            'files' => 'Files ('.$project->attachments->count().')',
            'notes' => 'Notes ('.$project->notes->count().')',
        ];
        $next = $project->followUps->where('status', 'pending')->sortBy('due_date')->first();
    @endphp

    {{-- Header --}}
    <div class="flex flex-col xl:flex-row xl:items-start justify-between gap-4 mb-6">
        <div class="min-w-0 flex-1">
            <a href="{{ route('projects.index') }}" class="text-[13px] text-muted hover:text-ink inline-flex items-center gap-1"><x-icon name="chevron-left" class="size-3.5" /> Projects</a>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <h1 class="text-[22px] font-semibold">{{ $project->name }}</h1>
                <x-pill :status="$project->status" :label="\App\Models\Project::STATUSES[$project->status]" />
            </div>
            <div class="text-muted mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[13px]">
                <span class="inline-flex items-center gap-1"><x-icon name="hash" class="size-3.5" />{{ $project->code }}</span>
                @if ($project->client)<span class="inline-flex items-center gap-1"><x-icon name="building-2" class="size-3.5" />{{ $project->client->displayName() }}@if ($project->client->phone) · {{ $project->client->phone }}@endif</span>@endif
                @if ($project->site_address || $project->city)<span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="size-3.5" />{{ collect([$project->site_address, $project->city])->filter()->implode(', ') }}</span>@endif
                @if ($project->manager)<span class="inline-flex items-center gap-1"><x-icon name="user" class="size-3.5" />{{ $project->manager->name }}</span>@endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2 xl:shrink-0">
            <form method="POST" action="{{ route('projects.status', $project) }}" x-data>
                @csrf @method('PATCH')
                <select name="status" class="input !h-9 !w-36" @change="$el.form.submit()">
                    @foreach (\App\Models\Project::STATUSES as $k => $l)<option value="{{ $k }}" @selected($project->status === $k)>{{ $l }}</option>@endforeach
                </select>
            </form>
            <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary"><x-icon name="pencil" /> Edit</a>
            <button class="btn btn-secondary" @click="$store.drawer.open('log-document-{{ $project->id }}')"><x-icon name="mail" /> Log document</button>
            <a href="{{ route('quotations.create', ['project' => $project->id]) }}" class="btn btn-primary"><x-icon name="plus" /> Quotation</a>
        </div>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        @foreach (['quoted' => 'Quoted', 'invoiced' => 'Invoiced', 'received' => 'Received', 'outstanding' => 'Outstanding', 'expenses' => 'Expenses'] as $key => $label)
            <div class="card card-pad">
                <div class="text-[13px] text-muted">{{ $label }}</div>
                <div @class(['text-xl font-semibold mt-1 tabular-nums', 'text-amber-600' => $key === 'outstanding' && $kpis[$key] > 0, 'text-emerald-600' => $key === 'received' && $kpis[$key] > 0])>{{ money($kpis[$key]) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-[1fr_300px] gap-6">
        <div class="min-w-0">
            <nav class="tabs mb-5">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('projects.show', [$project, 'tab' => $key]) }}" @class(['tab', 'tab-active' => $tab === $key])>{{ $label }}</a>
                @endforeach
            </nav>

            @switch($tab)
                @case('follow-ups')
                    <div class="card">
                        @forelse ($project->followUps as $f)
                            <div class="flex items-center gap-3 px-5 py-3 border-b border-line last:border-0">
                                <span @class(['size-2.5 rounded-full shrink-0', 'bg-emerald-500' => $f->status === 'done', 'bg-red-500' => $f->isOverdue(), 'bg-amber-400' => $f->status === 'pending' && ! $f->isOverdue()])></span>
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
                                        <x-action :url="route('notes.destroy', $note)" method="DELETE" confirm="Delete this note?" class="btn btn-icon hover:text-red-600"><x-icon name="trash-2" /></x-action>
                                    </div>
                                </div>
                                <p class="whitespace-pre-line">{{ $note->body }}</p>
                            </div>
                        @endforeach
                    </div>
                    @break

                @default
                    @if ($project->description)
                        <div class="card card-pad mb-6">
                            <h2 class="card-title mb-2">Scope</h2>
                            <p class="text-[13.5px] whitespace-pre-line text-muted">{{ $project->description }}</p>
                        </div>
                    @endif
                    <h2 class="card-title mb-3">Activity timeline</h2>
                    <ol class="relative border-l border-line ml-3 space-y-5">
                        @forelse ($timeline as $entry)
                            @if ($entry['kind'] === 'log')
                                @php $log = $entry['item']; @endphp
                                <li class="ml-6">
                                    <span class="absolute -left-[13px] grid place-items-center size-6 rounded-full bg-accent-soft text-accent ring-4 ring-bg"><x-icon name="paperclip" class="size-3.5" /></span>
                                    <div class="card p-4" x-data="{ open: false }">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="pill pill-teal">Logged</span>
                                            <span class="font-medium">{{ \App\Models\DocumentLog::TYPES[$log->document_type] }} {{ $log->reference_no }}</span>
                                            @if ($log->amount)<span class="text-muted">· {{ money($log->amount) }}</span>@endif
                                            <x-pill :status="$log->status" class="ml-auto" />
                                        </div>
                                        <div class="text-xs text-muted mt-1">
                                            Sent by {{ $log->creator?->name ?? '—' }} to {{ $log->sent_to_name ?: '—' }}@if ($log->sent_to_email) &lt;{{ $log->sent_to_email }}&gt;@endif · {{ $log->sent_date->format('d M Y') }}
                                            @if ($log->attachments->isNotEmpty()) · <x-icon name="paperclip" class="size-3 inline" /> {{ $log->attachments->count() }} file(s)@endif
                                        </div>
                                        @if ($log->last_discussion)
                                            <div class="mt-3 rounded-lg bg-surface-2 px-3 py-2 text-[13px]"><span class="font-medium">Last discussion:</span> {{ $log->last_discussion }}</div>
                                        @endif
                                        @if ($log->email_subject || $log->email_body)
                                            <button class="mt-2 text-xs link inline-flex items-center gap-1" @click="open = !open"><x-icon name="mail" class="size-3.5" /> <span x-text="open ? 'Hide email' : 'Show email'"></span>@if ($log->email_subject): {{ \Illuminate\Support\Str::limit($log->email_subject, 60) }}@endif</button>
                                            <div x-show="open" x-cloak class="mt-2 rounded-lg border border-line p-3 text-[13px] whitespace-pre-line">{{ $log->email_body }}</div>
                                        @endif
                                        @if ($log->attachments->isNotEmpty())
                                            <div class="flex flex-wrap gap-2 mt-3">
                                                @foreach ($log->attachments as $file)
                                                    <a href="{{ route('attachments.download', [$file, 'inline' => 1]) }}" target="_blank" class="chip"><x-icon name="paperclip" class="size-3.5" />{{ \Illuminate\Support\Str::limit($file->original_name, 30) }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @else
                                @php $a = $entry['item']; @endphp
                                <li class="ml-6">
                                    <span class="absolute -left-[7px] mt-1.5 size-3 rounded-full bg-line ring-4 ring-bg"></span>
                                    <div class="text-[13.5px]"><span class="font-medium">{{ $a->user?->name ?? 'System' }}</span> {{ lcfirst($a->description) }}</div>
                                    <div class="text-xs text-muted">{{ $a->created_at->format('d M Y, g:i a') }}</div>
                                </li>
                            @endif
                        @empty
                            <li class="ml-6 text-muted">No activity yet.</li>
                        @endforelse
                    </ol>
            @endswitch
        </div>

        {{-- Right rail --}}
        <aside class="space-y-4">
            <div class="card card-pad">
                <div class="section-title mb-2">Next follow-up</div>
                @if ($next)
                    <div @class(['font-medium', 'text-red-600' => $next->isOverdue()])>{{ $next->due_date->format('D, d M Y') }}</div>
                    <div class="text-[13.5px] mt-0.5">{{ $next->title }}</div>
                    <div class="text-xs text-muted mt-0.5">{{ $next->assignee?->name ?? 'Unassigned' }}</div>
                    <div class="flex gap-2 mt-3">
                        <x-action :url="route('follow-ups.done', $next)" class="btn btn-secondary btn-sm flex-1"><x-icon name="check" /> Done</x-action>
                        <x-action :url="route('follow-ups.snooze', $next)" data-days="2" class="btn btn-ghost btn-sm">Snooze</x-action>
                    </div>
                @else
                    <p class="text-muted text-[13px]">Nothing scheduled.</p>
                @endif
                <button class="btn btn-primary w-full mt-3" @click="$store.drawer.open('follow-up-{{ $project->id }}')"><x-icon name="plus" /> Add follow-up</button>
            </div>
            <div class="card card-pad text-[13px] space-y-2">
                <div class="section-title mb-1">Details</div>
                <div class="flex justify-between"><span class="text-muted">Value</span><span class="font-medium">{{ money($project->project_value) }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Start</span><span>{{ $project->start_date?->format('d M Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">End</span><span>{{ $project->end_date?->format('d M Y') ?? '—' }}</span></div>
                @if ($project->client?->email)<div class="flex justify-between gap-2"><span class="text-muted">Email</span><a href="mailto:{{ $project->client->email }}" class="link truncate">{{ $project->client->email }}</a></div>@endif
            </div>
        </aside>
    </div>

    <x-slot:drawers>
        @include('partials.drawers.follow-up', ['project' => $project, 'users' => $users, 'projects' => collect()])
        @include('partials.drawers.log-document', ['project' => $project, 'projects' => collect([$project])])
    </x-slot:drawers>
</x-layouts.app>
