{{--
    Shared quotation / invoice editor: form + line items on the left, live branded preview on the right.
    Expects: $doc, $kind ('Quotation'|'Invoice'), $number, $items, $projects, $action, $method
--}}
@php
    $oldItems = old('items', $items);
    $init = [
        'items' => array_values(array_map(fn ($i) => ['description' => $i['description'] ?? '', 'quantity' => (float) ($i['quantity'] ?? 1), 'unit' => $i['unit'] ?? '', 'rate' => (float) ($i['rate'] ?? 0)], $oldItems)),
        'discountType' => old('discount_type', $doc->discount_type ?? 'amount'),
        'discountValue' => (float) old('discount_value', $doc->discount_value ?? 0),
        'taxRate' => (float) old('tax_rate', $doc->tax_rate ?? 0),
        'currency' => \App\Models\Setting::get('finance.currency'),
    ];
    $projectMap = $projects->mapWithKeys(fn ($p) => [$p->id => ['name' => $p->name, 'client' => $p->client?->displayName(), 'address' => $p->client?->address, 'site' => $p->site_address]]);
    $isQuote = $kind === 'Quotation';
@endphp
<form method="POST" action="{{ $action }}" x-data="{ ...lineItems(@js($init)), project: '{{ old('project_id', $doc->project_id) }}', projects: @js($projectMap), title: @js(old('title', $doc->title ?? '')), issue: '{{ old('issue_date', optional($doc->issue_date)->format('Y-m-d')) }}' }" class="pb-24">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <a href="{{ url()->previous() }}" class="btn btn-icon"><x-icon name="chevron-left" /></a>
        <h1 class="text-[22px] font-semibold">{{ $doc->exists ? 'Edit' : 'New' }} {{ strtolower($kind) }}</h1>
        <span class="pill pill-gray">{{ $number }}</span>
        @if ($isQuote)<span class="pill pill-teal">Rev {{ $doc->revision }}</span>@endif
    </div>

    <div class="grid xl:grid-cols-2 gap-6">
        {{-- Left: form --}}
        <div class="space-y-6 min-w-0">
            <div class="card card-pad space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="label">Project <span class="text-red-500">*</span></label>
                        <select name="project_id" class="input" x-model="project" required>
                            <option value="">Choose project…</option>
                            @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }} · {{ $p->code }}</option>@endforeach
                        </select>
                        @error('project_id')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    @if ($isQuote)
                        <div class="sm:col-span-2">
                            <label class="label">Title <span class="text-red-500">*</span></label>
                            <input name="title" x-model="title" class="input" required placeholder="Interior renovation – Phase 1">
                            @error('title')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div>
                        <label class="label">Issue date</label>
                        <input type="date" name="issue_date" x-model="issue" class="input" required>
                    </div>
                    @if ($isQuote)
                        <x-field label="Valid until" name="valid_until" type="date" :value="$doc->valid_until" />
                    @else
                        <x-field label="Due date" name="due_date" type="date" :value="$doc->due_date" />
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="px-5 pt-5 pb-3 flex items-center justify-between">
                    <h2 class="card-title">Line items</h2>
                    <button type="button" class="btn btn-ghost btn-sm" @click="add()"><x-icon name="plus" /> Add line</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-fixed min-w-[520px] [&_td]:!px-2 [&_th]:!px-2">
                        <colgroup><col><col class="w-16"><col class="w-16"><col class="w-24"><col class="w-24"><col class="w-9"></colgroup>
                        <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th class="text-right">Amount</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="(item, i) in items" :key="i">
                                <tr class="hover:!bg-transparent">
                                    <td class="!py-2"><input :name="`items[${i}][description]`" x-model="item.description" class="input !h-9" placeholder="e.g. False ceiling with gypsum board"></td>
                                    <td class="!py-2"><input :name="`items[${i}][quantity]`" x-model="item.quantity" type="number" step="0.01" min="0" class="input !h-9 !px-2"></td>
                                    <td class="!py-2"><input :name="`items[${i}][unit]`" x-model="item.unit" class="input !h-9 !px-2" placeholder="sq ft"></td>
                                    <td class="!py-2"><input :name="`items[${i}][rate]`" x-model="item.rate" type="number" step="0.01" min="0" class="input !h-9 !px-2"></td>
                                    <td class="!py-2 text-right tabular-nums font-medium" x-text="num(amount(item))"></td>
                                    <td class="!py-2 !px-1"><button type="button" class="btn btn-icon !size-8 hover:text-red-600" @click="remove(i)" title="Remove">&times;</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                @error('items')<p class="error px-5">{{ $message }}</p>@enderror
                <div class="border-t border-line px-5 py-4 grid sm:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <div>
                            <label class="label">Discount</label>
                            <div class="flex gap-2">
                                <select name="discount_type" x-model="discountType" class="input !w-28"><option value="amount">PKR</option><option value="percent">%</option></select>
                                <input name="discount_value" x-model="discountValue" type="number" step="0.01" min="0" class="input">
                            </div>
                        </div>
                        <div>
                            <label class="label">{{ \App\Models\Setting::get('finance.tax_label') }} rate (%)</label>
                            <input name="tax_rate" x-model="taxRate" type="number" step="0.01" min="0" max="100" class="input">
                        </div>
                    </div>
                    <dl class="space-y-2 text-sm self-end">
                        <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular-nums" x-text="fmt(subtotal)"></dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Discount</dt><dd class="tabular-nums" x-text="'− ' + fmt(discount)"></dd></div>
                        <div class="flex justify-between"><dt class="text-muted">{{ \App\Models\Setting::get('finance.tax_label') }}</dt><dd class="tabular-nums" x-text="fmt(tax)"></dd></div>
                        <div class="flex justify-between border-t border-line pt-2 text-base font-semibold"><dt>Total</dt><dd class="tabular-nums" x-text="fmt(total)"></dd></div>
                    </dl>
                </div>
            </div>

            <div class="card card-pad grid sm:grid-cols-2 gap-4">
                <x-field label="Notes" name="notes" type="textarea" rows="4" :value="$doc->notes" placeholder="Shown on the document" />
                <x-field label="Terms" name="terms" type="textarea" rows="4" :value="$doc->terms" />
            </div>
        </div>

        {{-- Right: live branded preview --}}
        <div class="hidden xl:block">
            <div class="sticky top-24">
                <div class="section-title mb-2 flex items-center gap-1.5"><x-icon name="eye" class="size-3.5" /> Live preview</div>
                <div class="bg-white text-gray-900 rounded-card shadow-card border border-line p-8 text-[12px] leading-relaxed min-h-[700px]">
                    <div class="flex justify-between items-start border-b-2 border-teal-700 pb-4">
                        <div>
                            <div class="flex items-center gap-2"><span class="grid place-items-center size-9 rounded-lg bg-teal-700 text-white font-bold text-xs">NR</span><span class="text-base font-semibold">{{ \App\Models\Setting::get('company.name') }}</span></div>
                            <div class="text-gray-500 mt-1 max-w-56">{{ \App\Models\Setting::get('company.address') }}<br>{{ \App\Models\Setting::get('company.phone') }} · {{ \App\Models\Setting::get('company.email') }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-semibold text-teal-700 uppercase tracking-wide">{{ $kind }}</div>
                            <div class="text-gray-500">{{ $number }}@if ($isQuote) · Rev {{ $doc->revision }}@endif</div>
                            <div class="text-gray-500" x-text="issue ? new Date(issue + 'T00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-6 py-4">
                        <div><div class="text-[10px] uppercase tracking-wider text-gray-400">Bill to</div><div class="font-medium" x-text="projects[project]?.client || '—'"></div><div class="text-gray-500" x-text="projects[project]?.address || ''"></div></div>
                        <div><div class="text-[10px] uppercase tracking-wider text-gray-400">Project</div><div class="font-medium" x-text="projects[project]?.name || '—'"></div><div class="text-gray-500" x-text="projects[project]?.site || ''"></div></div>
                    </div>
                    @if ($isQuote)<div class="font-semibold mb-2" x-text="title"></div>@endif
                    <table class="w-full">
                        <thead><tr class="bg-gray-50 text-gray-500 text-[10px] uppercase tracking-wider"><th class="text-left p-2">#</th><th class="text-left p-2">Description</th><th class="text-right p-2">Qty</th><th class="text-right p-2">Rate</th><th class="text-right p-2">Amount</th></tr></thead>
                        <tbody>
                            <template x-for="(item, i) in items.filter(x => x.description)" :key="i">
                                <tr class="border-b border-gray-100"><td class="p-2 text-gray-400" x-text="i + 1"></td><td class="p-2" x-text="item.description"></td><td class="p-2 text-right" x-text="num(item.quantity) + ' ' + (item.unit || '')"></td><td class="p-2 text-right" x-text="num(item.rate)"></td><td class="p-2 text-right font-medium" x-text="num(amount(item))"></td></tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="flex justify-end mt-4">
                        <dl class="w-56 space-y-1">
                            <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd x-text="fmt(subtotal)"></dd></div>
                            <div class="flex justify-between" x-show="discount > 0"><dt class="text-gray-500">Discount</dt><dd x-text="'− ' + fmt(discount)"></dd></div>
                            <div class="flex justify-between" x-show="tax > 0"><dt class="text-gray-500">{{ \App\Models\Setting::get('finance.tax_label') }}</dt><dd x-text="fmt(tax)"></dd></div>
                            <div class="flex justify-between border-t-2 border-teal-700 pt-1.5 font-semibold text-[13px]"><dt>Total</dt><dd x-text="fmt(total)"></dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sticky footer --}}
    <div class="fixed bottom-16 lg:bottom-0 inset-x-0 z-20 bg-surface/95 backdrop-blur border-t border-line" :class="$store.sidebar.collapsed ? 'lg:left-[72px]' : 'lg:left-60'">
        <div class="flex items-center justify-between gap-3 px-4 lg:px-8 py-3">
            <div class="text-sm"><span class="text-muted">Total</span> <span class="font-semibold text-base tabular-nums" x-text="fmt(total)"></span></div>
            <div class="flex gap-2">
                <button name="action" value="draft" class="btn btn-secondary"><x-icon name="check" /> Save {{ $doc->status === 'draft' ? 'draft' : '' }}</button>
                @if ($doc->status === 'draft')
                    <button name="action" value="send" class="btn btn-primary"><x-icon name="send" /> Save &amp; mark sent</button>
                @endif
            </div>
        </div>
    </div>
</form>
