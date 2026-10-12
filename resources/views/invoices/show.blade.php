<x-layouts.app :title="$invoice->invoice_number">
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('projects.show', [$invoice->project, 'tab' => 'invoices']) }}" class="text-[13px] text-muted hover:text-ink inline-flex items-center gap-1"><x-icon name="chevron-left" class="size-3.5" /> {{ $invoice->project->name }}</a>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <h1 class="text-[22px] font-semibold">{{ $invoice->invoice_number }}</h1>
                <x-pill :status="$invoice->status" />
            </div>
            <p class="text-muted mt-1">{{ money($invoice->total) }} · balance {{ money($invoice->balance_due) }}@if ($invoice->quotation) · from <a class="link" href="{{ route('quotations.show', $invoice->quotation) }}">{{ $invoice->quotation->label() }}</a>@endif</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" /> Print / PDF</a>
            @can('invoices.manage')
            @if ($invoice->isEditable())<a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-secondary"><x-icon name="pencil" /> Edit</a>@endif
            @if ($invoice->status === 'draft')
                <x-action :url="route('invoices.status', $invoice)" data-status="sent" class="btn btn-primary"><x-icon name="send" /> Mark sent</x-action>
            @elseif (in_array($invoice->status, ['sent', 'partially_paid', 'overdue']))
                <button class="btn btn-primary" @click="$store.drawer.open('payment')"><x-icon name="banknote" /> Record payment</button>
            @endif
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-6">
        @include('partials.doc-preview', ['doc' => $invoice, 'kind' => 'Invoice'])
        <aside class="space-y-4">
            <div class="card card-pad">
                <div class="section-title mb-2">Payment progress</div>
                @php $pct = $invoice->total > 0 ? min(100, round($invoice->amount_paid / $invoice->total * 100)) : 0; @endphp
                <div class="flex justify-between text-[13px] mb-1.5"><span>{{ money($invoice->amount_paid) }} received</span><span class="text-muted">{{ $pct }}%</span></div>
                <div class="bar"><span class="bg-green-fg" style="width: {{ $pct }}%"></span></div>
            </div>
            <div class="card">
                <div class="px-5 pt-5 pb-2 section-title">Payments</div>
                @forelse ($invoice->payments as $p)
                    <div class="flex items-center gap-3 px-5 py-2.5 border-t border-line">
                        <div class="flex-1 min-w-0">
                            <div class="font-medium tabular-nums">{{ money($p->amount) }}</div>
                            <div class="text-xs text-muted">{{ $p->payment_date->format('d M Y') }} · {{ $methods[$p->method] ?? $p->method }}@if ($p->reference_no) · {{ $p->reference_no }}@endif</div>
                        </div>
                        @can('invoices.manage')<x-action :url="route('payments.destroy', $p)" method="DELETE" confirm="Remove this payment?" class="btn btn-icon hover:text-red-fg"><x-icon name="trash-2" /></x-action>@endcan
                    </div>
                @empty
                    <p class="px-5 pb-5 text-[13px] text-muted">No payments yet.</p>
                @endforelse
            </div>
            @if (! in_array($invoice->status, ['paid', 'cancelled']) && auth()->user()->hasPermission('invoices.manage'))
                <x-action :url="route('invoices.status', $invoice)" data-status="cancelled" confirm="Cancel this invoice?" class="btn btn-ghost btn-sm w-full">Cancel invoice</x-action>
            @endif
        </aside>
    </div>

    <x-slot:drawers>
        @if (request()->boolean('pay'))
            <script>document.addEventListener('alpine:initialized', () => Alpine.store('drawer').open('payment'));</script>
        @endif
        <x-drawer name="payment" title="Record payment" :subtitle="$invoice->invoice_number.' · balance '.money($invoice->balance_due)">
            <form id="payment-form" method="POST" action="{{ route('payments.store', $invoice) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_drawer" value="payment">
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Amount (PKR)" name="amount" type="number" step="0.01" min="1" :max="$invoice->balance_due" :value="$invoice->balance_due" required />
                    <x-field label="Date received" name="payment_date" type="date" :value="today()->format('Y-m-d')" required />
                </div>
                <x-field label="Method" name="method" :options="$methods" value="bank_transfer" />
                <x-field label="Reference no." name="reference_no" placeholder="Cheque / transaction ID" />
                <x-field label="Notes" name="notes" type="textarea" rows="3" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
                <button form="payment-form" class="btn btn-primary"><x-icon name="check" /> Record payment</button>
            </x-slot:footer>
        </x-drawer>
    </x-slot:drawers>
    <script>document.addEventListener('alpine:initialized', () => { if (location.hash === '#payment') Alpine.store('drawer').open('payment'); });</script>
</x-layouts.app>
