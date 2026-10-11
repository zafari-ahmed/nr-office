<x-layouts.app title="Sent log">
    <x-page-header title="Sent documents log" subtitle="Quotations and invoices prepared outside the system, with the email and what was discussed.">
        <x-slot:actions>
            <button class="btn btn-primary" @click="$store.drawer.open('log-document')"><x-icon name="plus" /> Log sent document</button>
        </x-slot:actions>
    </x-page-header>

    <form class="flex flex-wrap gap-2 mb-4">
        <select name="type" class="input !h-9 !w-40" onchange="this.form.submit()">
            <option value="">All types</option>
            @foreach (\App\Models\DocumentLog::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="status" class="input !h-9 !w-44" onchange="this.form.submit()">
            <option value="">Any status</option>
            @foreach (\App\Models\DocumentLog::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="project" class="input !h-9 !w-64" onchange="this.form.submit()">
            <option value="">All projects</option>
            @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Sent</th><th>Document</th><th>Project</th><th>Sent to</th><th>Next follow-up</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr class="cursor-pointer" onclick="location='{{ route('document-logs.show', $log) }}'">
                        <td class="whitespace-nowrap">{{ $log->sent_date->format('d M Y') }}</td>
                        <td>
                            <div class="font-medium flex items-center gap-1.5">{{ \App\Models\DocumentLog::TYPES[$log->document_type] }} {{ $log->reference_no }}@if ($log->attachments->isNotEmpty())<x-icon name="paperclip" class="size-3.5 text-muted" />@endif</div>
                            <div class="text-xs text-muted truncate max-w-64">{{ $log->email_subject }}</div>
                        </td>
                        <td>{{ $log->project->name }}</td>
                        <td><div>{{ $log->sent_to_name ?: '—' }}</div><div class="text-xs text-muted">{{ $log->sent_to_email }}</div></td>
                        <td @class(['whitespace-nowrap', 'text-red-fg' => $log->next_follow_up_date?->isPast()])>{{ $log->next_follow_up_date?->format('d M Y') ?? '—' }}</td>
                        <td><x-pill :status="$log->status" /></td>
                        <td class="text-right tabular-nums">{{ $log->amount ? money($log->amount) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="mail" title="Nothing logged yet" text="When you email a quotation or invoice made in Word or Excel, log it here so the follow-up is never missed."><button class="btn btn-primary" @click="$store.drawer.open('log-document')">Log sent document</button></x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
</x-layouts.app>
