{{-- "Log sent document": record a quotation / invoice made outside the system. --}}
<x-drawer name="log-document{{ $project ? '-'.$project->id : '' }}" title="Log sent document" subtitle="For quotations or invoices made outside the system" width="max-w-xl">
    <form id="log-form{{ $project?->id }}" method="POST" action="{{ route('document-logs.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        <input type="hidden" name="_drawer" value="log-document{{ $project ? '-'.$project->id : '' }}">
        <div class="grid grid-cols-2 gap-4">
            <x-field label="Project" name="project_id" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->code])" blank="Choose project…" :value="$project?->id" required class="col-span-2" />
            <div>
                <label class="label">Type <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2" x-data="{ t: '{{ old('document_type', 'quotation') }}' }">
                    @foreach (\App\Models\DocumentLog::TYPES as $value => $label)
                        <label :class="t === '{{ $value }}' ? 'border-accent bg-accent-soft text-accent' : 'border-line'" class="flex items-center justify-center h-10 rounded-lg border text-sm font-medium cursor-pointer">
                            <input type="radio" name="document_type" value="{{ $value }}" x-model="t" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <x-field label="Reference no." name="reference_no" placeholder="QT-0142" />
            <x-field label="Amount (PKR)" name="amount" type="number" step="0.01" min="0" hint="Optional" />
            <x-field label="Date sent" name="sent_date" type="date" :value="today()->format('Y-m-d')" required />
        </div>

        <div>
            <div class="section-title mb-2">Sent to</div>
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Contact name" name="sent_to_name" placeholder="Mr. Imran Qureshi" />
                <x-field label="Email" name="sent_to_email" type="email" placeholder="imran@client.pk" />
            </div>
        </div>

        <x-field label="What was discussed last time" name="last_discussion" type="textarea" rows="3" placeholder="Client asked to reduce the false ceiling scope and use local tiles…" />

        <div>
            <div class="section-title mb-2">Email</div>
            <div class="space-y-3">
                <x-field label="Subject" name="email_subject" placeholder="Revised quotation – Gulberg Residence Renovation" />
                <x-field label="Email content" name="email_body" type="textarea" rows="6" placeholder="Paste the email you sent…" />
            </div>
        </div>

        <x-dropzone name="files[]" label="Attached quotation / invoice" />

        <div class="grid grid-cols-2 gap-4">
            <x-field label="Next follow-up date" name="next_follow_up_date" type="date" hint="Creates a follow-up on the project" />
            <x-field label="Status" name="status" :options="\App\Models\DocumentLog::STATUSES" required />
        </div>
    </form>
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
        <button form="log-form{{ $project?->id }}" class="btn btn-primary"><x-icon name="check" /> Save log</button>
    </x-slot:footer>
</x-drawer>
