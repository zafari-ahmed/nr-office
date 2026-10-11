{{-- Read-only branded document (used on show pages and print). --}}
@php $isQuote = $kind === 'Quotation'; @endphp
<div class="bg-white text-gray-900 rounded-card shadow-card border border-line p-6 sm:p-10 text-[13px] leading-relaxed print:shadow-none print:border-0 print:p-0">
    <div class="flex flex-col sm:flex-row justify-between items-start gap-4 border-b-2 border-teal-700 pb-5">
        <div>
            <div class="flex items-center gap-2.5"><span class="grid place-items-center size-10 rounded-lg bg-teal-700 text-white font-bold text-sm">NR</span><span class="text-lg font-semibold">{{ \App\Models\Setting::get('company.name') }}</span></div>
            <div class="text-gray-500 mt-1.5 max-w-xs">{{ \App\Models\Setting::get('company.address') }}<br>{{ \App\Models\Setting::get('company.phone') }} · {{ \App\Models\Setting::get('company.email') }}@if (\App\Models\Setting::get('company.ntn'))<br>NTN {{ \App\Models\Setting::get('company.ntn') }}@endif</div>
        </div>
        <div class="sm:text-right">
            <div class="text-2xl font-semibold text-teal-700 uppercase tracking-wide">{{ $kind }}</div>
            <div class="text-gray-600 font-medium">{{ $isQuote ? $doc->quotation_number.' · Rev '.$doc->revision : $doc->invoice_number }}</div>
            <div class="text-gray-500">Date: {{ $doc->issue_date->format('d M Y') }}</div>
            @if ($isQuote && $doc->valid_until)<div class="text-gray-500">Valid until: {{ $doc->valid_until->format('d M Y') }}</div>@endif
            @if (! $isQuote && $doc->due_date)<div class="text-gray-500">Due: {{ $doc->due_date->format('d M Y') }}</div>@endif
        </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-6 py-5">
        <div>
            <div class="text-[10px] uppercase tracking-wider text-gray-400">Bill to</div>
            <div class="font-medium">{{ $doc->project->client?->displayName() ?? '—' }}</div>
            @if ($doc->project->client?->contact_person)<div class="text-gray-500">Attn: {{ $doc->project->client->contact_person }}</div>@endif
            <div class="text-gray-500">{{ $doc->project->client?->address }}</div>
        </div>
        <div>
            <div class="text-[10px] uppercase tracking-wider text-gray-400">Project</div>
            <div class="font-medium">{{ $doc->project->name }} <span class="text-gray-400 font-normal">({{ $doc->project->code }})</span></div>
            <div class="text-gray-500">{{ $doc->project->site_address }}</div>
        </div>
    </div>
    @if ($isQuote)<div class="font-semibold text-[15px] mb-3">{{ $doc->title }}</div>@endif
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr class="bg-gray-50 text-gray-500 text-[11px] uppercase tracking-wider"><th class="text-left p-2.5 w-8">#</th><th class="text-left p-2.5">Description</th><th class="text-right p-2.5">Qty</th><th class="text-right p-2.5">Rate</th><th class="text-right p-2.5">Amount</th></tr></thead>
            <tbody>
            @foreach ($doc->items as $i => $item)
                <tr class="border-b border-gray-100">
                    <td class="p-2.5 text-gray-400">{{ $i + 1 }}</td>
                    <td class="p-2.5">{{ $item->description }}</td>
                    <td class="p-2.5 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="p-2.5 text-right">{{ money($item->rate, false) }}</td>
                    <td class="p-2.5 text-right font-medium">{{ money($item->amount, false) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex justify-end mt-5">
        <dl class="w-full sm:w-72 space-y-1.5">
            <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ money($doc->subtotal) }}</dd></div>
            @if ($doc->discount_amount > 0)<div class="flex justify-between"><dt class="text-gray-500">Discount{{ $doc->discount_type === 'percent' ? ' ('.(float) $doc->discount_value.'%)' : '' }}</dt><dd>− {{ money($doc->discount_amount) }}</dd></div>@endif
            @if ($doc->tax_amount > 0)<div class="flex justify-between"><dt class="text-gray-500">{{ \App\Models\Setting::get('finance.tax_label') }} ({{ (float) $doc->tax_rate }}%)</dt><dd>{{ money($doc->tax_amount) }}</dd></div>@endif
            <div class="flex justify-between border-t-2 border-teal-700 pt-2 font-semibold text-[15px]"><dt>Total</dt><dd>{{ money($doc->total) }}</dd></div>
            @if (! $isQuote && $doc->amount_paid > 0)
                <div class="flex justify-between text-emerald-700"><dt>Paid</dt><dd>− {{ money($doc->amount_paid) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>Balance due</dt><dd>{{ money($doc->balance_due) }}</dd></div>
            @endif
        </dl>
    </div>
    @if ($doc->notes || $doc->terms)
        <div class="grid sm:grid-cols-2 gap-6 mt-8 pt-5 border-t border-gray-100 text-gray-600">
            @if ($doc->notes)<div><div class="text-[10px] uppercase tracking-wider text-gray-400 mb-1">Notes</div><p class="whitespace-pre-line">{{ $doc->notes }}</p></div>@endif
            @if ($doc->terms)<div><div class="text-[10px] uppercase tracking-wider text-gray-400 mb-1">Terms</div><p class="whitespace-pre-line">{{ $doc->terms }}</p></div>@endif
        </div>
    @endif
</div>
