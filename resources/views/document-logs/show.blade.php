<x-layouts.app :title="'Log '.$log->reference_no">
    <div class="mb-6">
        <a href="{{ route('document-logs.index') }}" class="text-[13px] text-muted hover:text-ink inline-flex items-center gap-1"><x-icon name="chevron-left" class="size-3.5" /> Sent log</a>
        <div class="flex flex-wrap items-center gap-3 mt-1">
            <h1 class="text-[22px] font-semibold">{{ \App\Models\DocumentLog::TYPES[$log->document_type] }} {{ $log->reference_no }}</h1>
            <span class="pill pill-teal">Logged</span>
            <x-pill :status="$log->status" />
        </div>
        <p class="text-muted mt-1"><a href="{{ route('projects.show', $log->project) }}" class="link">{{ $log->project->name }}</a> · sent {{ $log->sent_date->format('d M Y') }} by {{ $log->creator?->name }}@if ($log->amount) · {{ money($log->amount) }}@endif</p>
    </div>

    <div class="grid lg:grid-cols-[1fr_320px] gap-6">
        <div class="space-y-6 min-w-0">
            @if ($log->last_discussion)
                <div class="card card-pad"><div class="section-title mb-2">What was discussed last time</div><p class="whitespace-pre-line">{{ $log->last_discussion }}</p></div>
            @endif
            <div class="card card-pad">
                <div class="section-title mb-2">Email</div>
                <div class="text-[13px] text-muted">To: {{ $log->sent_to_name }} @if ($log->sent_to_email)&lt;{{ $log->sent_to_email }}&gt;@endif</div>
                <div class="font-medium mt-1">{{ $log->email_subject ?: '(no subject)' }}</div>
                <div class="mt-3 pt-3 border-t border-line whitespace-pre-line text-[13.5px]">{{ $log->email_body ?: 'No email text pasted.' }}</div>
            </div>
            <div class="card card-pad">
                <div class="section-title mb-2">Attached files</div>
                @if ($log->attachments->isNotEmpty())<x-attachment-list :attachments="$log->attachments" />@else<p class="text-muted text-[13px]">No files attached.</p>@endif
            </div>
        </div>
        <aside class="space-y-4">
            <form method="POST" action="{{ route('document-logs.update', $log) }}" enctype="multipart/form-data" class="card card-pad space-y-4">
                @csrf @method('PUT')
                <div class="section-title">Update</div>
                @foreach (['project_id', 'document_type', 'reference_no', 'amount', 'sent_to_name', 'sent_to_email', 'last_discussion', 'email_subject', 'email_body'] as $f)
                    <input type="hidden" name="{{ $f }}" value="{{ $log->$f }}">
                @endforeach
                <input type="hidden" name="sent_date" value="{{ $log->sent_date->format('Y-m-d') }}">
                <x-field label="Status" name="status" :options="\App\Models\DocumentLog::STATUSES" :value="$log->status" />
                <x-field label="Next follow-up" name="next_follow_up_date" type="date" :value="$log->next_follow_up_date" />
                <x-dropzone label="Add files" />
                <button class="btn btn-primary w-full">Save</button>
            </form>
            @if ($log->followUp)
                <div class="card card-pad text-[13px]">
                    <div class="section-title mb-1">Linked follow-up</div>
                    <div class="font-medium">{{ $log->followUp->title }}</div>
                    <div class="text-muted">{{ $log->followUp->due_date->format('d M Y') }} · {{ status_label($log->followUp->status) }}</div>
                </div>
            @endif
            <x-action :url="route('document-logs.destroy', $log)" method="DELETE" confirm="Delete this log?" class="btn btn-ghost btn-sm w-full hover:text-red-600"><x-icon name="trash-2" /> Delete log</x-action>
        </aside>
    </div>
</x-layouts.app>
