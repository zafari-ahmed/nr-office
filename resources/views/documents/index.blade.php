<x-layouts.app title="Documents">
    <x-page-header title="Documents" subtitle="Write on your letterhead from a template, print, and keep a history of every print.">
        <x-slot:actions>
            @if (auth()->user()->hasPermission('settings.manage'))<a href="{{ route('settings.letterheads.index') }}" class="btn btn-secondary"><x-icon name="settings" /> Letterhead & templates</a>@endif
        </x-slot:actions>
    </x-page-header>

    @unless ($hasLetterhead)
        <div class="card card-pad mb-5 flex flex-wrap items-center gap-3 border-amber-fg/30 bg-amber-bg">
            <x-icon name="stamp" class="text-amber-fg" />
            <div class="flex-1 min-w-[220px] text-[13.5px]"><b>No letterhead yet.</b> Documents print on plain paper until a letterhead is uploaded in Settings › Letterhead & templates.</div>
            @if (auth()->user()->hasPermission('settings.manage'))<a href="{{ route('settings.letterheads.create') }}" class="btn btn-primary btn-sm">Upload letterhead</a>@endif
        </div>
    @endunless

    <div class="mb-6">
        <div class="text-[13px] font-medium text-ink-2 mb-2.5">Start a new document</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
            @foreach ($templates as $t)
                <a href="{{ route('documents.create', ['template' => $t->id]) }}" class="card card-pad no-underline hover:no-underline hover:border-accent/50 group">
                    <span class="grid place-items-center size-9 rounded-lg bg-accent-soft text-accent-ink mb-2.5"><x-icon name="file-text" /></span>
                    <div class="text-[14px] font-medium text-ink group-hover:text-accent">{{ $t->name }}</div>
                    <div class="text-[12px] text-muted font-mono">{{ $t->ref_prefix }}-{{ now()->year }}-…</div>
                </a>
            @endforeach
            <a href="{{ route('documents.create') }}" class="card card-pad no-underline hover:no-underline hover:border-accent/50 border-dashed group">
                <span class="grid place-items-center size-9 rounded-lg bg-surface-2 text-ink-2 mb-2.5"><x-icon name="plus" /></span>
                <div class="text-[14px] font-medium text-ink group-hover:text-accent">Blank page</div>
                <div class="text-[12px] text-muted">On the letterhead</div>
            </a>
        </div>
        @if ($templates->isEmpty() && auth()->user()->hasPermission('settings.manage'))
            <p class="hint mt-2">Add templates such as Quotation or Purchase order in <a href="{{ route('settings.letterheads.index') }}">Settings › Letterhead & templates</a>.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_300px] gap-6 items-start">
        <div class="card overflow-hidden min-w-0">
            <form method="GET" class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-line">
                <div class="relative flex-1 min-w-[200px]">
                    <x-icon name="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted !size-4" />
                    <input name="q" value="{{ request('q') }}" placeholder="Search reference, subject or recipient" class="input !h-9 !pl-8">
                </div>
                <select name="template" class="input !h-9 !w-auto" onchange="this.form.submit()">
                    <option value="">All templates</option>
                    @foreach ($allTemplates as $id => $name)<option value="{{ $id }}" @selected(request('template') == $id)>{{ $name }}</option>@endforeach
                </select>
                <select name="printed" class="input !h-9 !w-auto" onchange="this.form.submit()">
                    <option value="">Printed or not</option>
                    <option value="yes" @selected(request('printed') === 'yes')>Printed</option>
                    <option value="no" @selected(request('printed') === 'no')>Not printed yet</option>
                </select>
                <button class="btn btn-secondary btn-sm">Search</button>
            </form>
            @if ($documents->isEmpty())
                <x-empty icon="file-text" title="No documents yet" text="Pick a template above to write your first document on the letterhead." />
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Subject</th><th>Template</th><th>Date</th><th>Printed</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($documents as $d)
                            <tr>
                                <td class="font-mono text-[12.5px] whitespace-nowrap"><a href="{{ route('documents.show', $d) }}">{{ $d->ref_no }}</a></td>
                                <td class="min-w-[200px]">
                                    <a href="{{ route('documents.show', $d) }}" class="font-medium text-ink">{{ $d->title }}</a>
                                    <div class="text-[12px] text-muted">{{ collect([$d->recipient, $d->project?->name])->filter()->implode(' · ') ?: 'By '.($d->creator?->name ?? '—') }}</div>
                                </td>
                                <td class="text-ink-2 whitespace-nowrap">{{ $d->template?->name ?? 'Blank' }}</td>
                                <td class="text-ink-2 whitespace-nowrap">{{ $d->doc_date->format('d M Y') }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($d->print_count)
                                        <span class="font-medium">{{ $d->print_count }}×</span> <span class="text-[12px] text-muted">{{ $d->last_printed_at?->diffForHumans() }}</span>
                                    @else
                                        <span class="text-muted">Not yet</span>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('documents.print', [$d, 'go' => 1]) }}" target="_blank" class="btn btn-ghost btn-sm"><x-icon name="printer" /> Print</a>
                                    <a href="{{ route('documents.edit', $d) }}" class="btn btn-icon" title="Edit"><x-icon name="pencil" /></a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($documents->hasPages())<div class="px-4 py-3 border-t border-line">{{ $documents->links() }}</div>@endif
            @endif
        </div>

        <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-line flex items-center gap-2"><x-icon name="history" class="text-ink-2" /><h2 class="card-title !text-[14px]">Print history</h2></div>
            @forelse ($recentPrints as $p)
                <a href="{{ route('document-prints.show', $p) }}" target="_blank" class="flex items-start gap-2.5 px-4 py-2.5 border-b border-line-2 last:border-b-0 no-underline hover:no-underline hover:bg-surface-2">
                    <x-icon name="printer" class="text-muted mt-0.5 !size-4" />
                    <span class="min-w-0">
                        <span class="block text-[13px] font-medium text-ink truncate">{{ $p->document?->ref_no }} · {{ $p->title }}</span>
                        <span class="block text-[12px] text-muted">{{ $p->user?->name ?? 'Someone' }} · {{ $p->printed_at->format('d M Y, g:i a') }}@unless ($p->with_letterhead) · plain paper @endunless</span>
                    </span>
                </a>
            @empty
                <p class="px-4 py-6 text-center text-[13px] text-muted">Prints appear here with who printed what, and when.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
