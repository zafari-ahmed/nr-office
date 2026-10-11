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
    $cur = \App\Models\Setting::get('finance.currency');
    $tax = \App\Models\Setting::get('finance.tax_label');
    $s = fn ($k) => \App\Models\Setting::get($k);
@endphp
<form method="POST" action="{{ $action }}" x-data="lineItems(@js($init), { project: '{{ old('project_id', $doc->project_id) }}', projects: @js($projectMap), title: @js(old('title', $doc->title ?? '')), issue: '{{ old('issue_date', optional($doc->issue_date)->format('Y-m-d')) }}', until: '{{ old($isQuote ? 'valid_until' : 'due_date', optional($isQuote ? $doc->valid_until : $doc->due_date)->format('Y-m-d')) }}', terms: @js(old('terms', $doc->terms ?? '')), d: (v) => v ? new Date(v + 'T00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '' })"
      class="flex-1 flex flex-col min-h-[calc(100vh-56px)]">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    {{-- Top bar --}}
    <div class="flex flex-wrap items-center gap-3 px-4 lg:px-6 py-3 bg-surface border-b border-line">
        <a href="{{ url()->previous() }}" class="btn btn-icon" title="Back"><x-icon name="arrow-left" /></a>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-[15px] font-semibold">{{ $number }}</span>
                @if ($isQuote)<span class="tag bg-grey-bg text-grey-fg normal-case tracking-normal font-medium text-[12px]">Rev {{ $doc->revision }}</span>@endif
                <x-pill :status="$doc->status ?? 'draft'" />
                @unless ($doc->exists)<span class="text-[12.5px] text-muted">New {{ strtolower($kind) }}</span>@endunless
            </div>
            <div class="text-[12.5px] text-muted mt-0.5" x-text="projects[project] ? projects[project].name + (projects[project].client ? ' · ' + projects[project].client : '') : 'Choose a project'"></div>
        </div>
    </div>

    <div class="flex-1 grid xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        {{-- Left: form --}}
        <div class="min-w-0 p-4 lg:p-6 space-y-5">
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="label">Project <span class="text-red-fg">*</span></label>
                    <select name="project_id" class="input" x-model="project" required>
                        <option value="">Choose project…</option>
                        @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }} · {{ $p->code }}</option>@endforeach
                    </select>
                    @error('project_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Client</label>
                    <div class="input flex items-center bg-surface-2 text-ink-2 truncate" x-text="projects[project]?.client || '—'"></div>
                </div>
                @if ($isQuote)
                    <div class="sm:col-span-3">
                        <label class="label">Title <span class="text-red-fg">*</span></label>
                        <input name="title" x-model="title" class="input" required placeholder="Interior renovation – Phase 1">
                        @error('title')<p class="error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label class="label">{{ $isQuote ? 'Quote date' : 'Invoice date' }}</label>
                    <input type="date" name="issue_date" x-model="issue" class="input" required>
                </div>
                <div>
                    <label class="label">{{ $isQuote ? 'Valid until' : 'Due date' }}</label>
                    <input type="date" name="{{ $isQuote ? 'valid_until' : 'due_date' }}" x-model="until" class="input">
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-fixed min-w-[480px] [&_td]:!px-2 [&_th]:!px-2">
                        <colgroup><col class="w-9"><col><col class="w-16"><col class="w-16"><col class="w-28"><col class="w-28"><col class="w-8"></colgroup>
                        <thead><tr><th class="!pl-4">#</th><th>Description</th><th class="text-right">Qty</th><th>Unit</th><th class="text-right">Rate ({{ $cur }})</th><th class="text-right">Amount</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="(item, i) in items" :key="i">
                                <tr class="group">
                                    <td class="!pl-4 text-muted text-[13px]" x-text="i + 1"></td>
                                    <td><input :name="`items[${i}][description]`" x-model="item.description" class="w-full h-8 px-2 rounded-md bg-transparent outline-none border border-transparent hover:border-line focus:border-accent focus:bg-surface" placeholder="e.g. False ceiling with gypsum board"></td>
                                    <td><input :name="`items[${i}][quantity]`" x-model="item.quantity" type="number" step="0.01" min="0" class="no-spin w-full h-8 px-2 rounded-md bg-transparent outline-none text-right tabular-nums border border-transparent hover:border-line focus:border-accent focus:bg-surface"></td>
                                    <td><input :name="`items[${i}][unit]`" x-model="item.unit" class="w-full h-8 px-2 rounded-md bg-transparent outline-none text-ink-2 border border-transparent hover:border-line focus:border-accent focus:bg-surface" placeholder="sq ft"></td>
                                    <td><input :name="`items[${i}][rate]`" x-model="item.rate" type="number" step="0.01" min="0" class="no-spin w-full h-8 px-2 rounded-md bg-transparent outline-none text-right tabular-nums border border-transparent hover:border-line focus:border-accent focus:bg-surface"></td>
                                    <td class="text-right tabular-nums font-medium" x-text="num(amount(item))"></td>
                                    <td class="!px-1"><button type="button" class="btn btn-icon !size-7 !w-7 opacity-0 group-hover:opacity-100 focus:opacity-100 hover:text-red-fg" @click="remove(i)" title="Remove line"><x-icon name="x" class="!size-3.5" /></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-2.5 border-t border-line-2">
                    <button type="button" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-accent" @click="add()"><x-icon name="plus" class="size-4" /> Add line item</button>
                </div>
                @error('items')<p class="error px-4 pb-3">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-4 items-start">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Discount</label>
                            <div class="flex">
                                <input name="discount_value" x-model="discountValue" type="number" step="0.01" min="0" class="input !rounded-r-none">
                                <select name="discount_type" x-model="discountType" class="input !w-[72px] !rounded-l-none -ml-px !px-2"><option value="percent">%</option><option value="amount">{{ $cur }}</option></select>
                            </div>
                        </div>
                        <div>
                            <label class="label">{{ $tax }} (%)</label>
                            <input name="tax_rate" x-model="taxRate" type="number" step="0.01" min="0" max="100" class="input">
                        </div>
                    </div>
                    <div>
                        <label class="label">Terms</label>
                        <textarea name="terms" x-model="terms" rows="3" class="input"></textarea>
                    </div>
                    <x-field label="Notes" name="notes" type="textarea" rows="2" :value="$doc->notes" placeholder="Shown on the document" />
                </div>
                <dl class="card p-4 space-y-2 text-[13.5px]">
                    <div class="flex justify-between"><dt class="text-ink-2">Subtotal</dt><dd class="tabular-nums" x-text="fmt(subtotal)"></dd></div>
                    <div class="flex justify-between" x-show="discount > 0"><dt class="text-ink-2" x-text="discountType === 'percent' ? `Discount (${num(discountValue)}%)` : 'Discount'"></dt><dd class="tabular-nums text-green-fg" x-text="'− ' + fmt(discount)"></dd></div>
                    <div class="flex justify-between"><dt class="text-ink-2" x-text="`{{ $tax }} (${num(taxRate)}%)`"></dt><dd class="tabular-nums" x-text="fmt(tax)"></dd></div>
                    <div class="flex items-baseline justify-between border-t border-line pt-3 mt-1"><dt class="font-semibold">Total</dt><dd class="tabular-nums text-[22px] font-semibold tracking-[-.01em]" x-text="fmt(total)"></dd></div>
                </dl>
            </div>
        </div>

        {{-- Right: live A4 preview --}}
        <div class="hidden xl:block bg-bg2 border-l border-line p-6">
            <div class="sticky top-[80px]">
                <div class="flex items-center justify-between text-[12.5px] text-muted mb-2.5">
                    <span>Live PDF preview</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="download" class="size-3.5" /> A4</span>
                </div>
                <div class="bg-white text-[#14171a] shadow-pop border-b-4 border-[#0e6b69] aspect-[1/1.414] px-9 py-8 text-[10.5px] leading-snug flex flex-col">
                    <div class="flex justify-between items-start gap-6 pb-4 border-b border-[#e4e7ea]">
                        <div class="flex gap-2.5">
                            <span class="grid place-items-center size-8 rounded-md bg-[#0e6b69] text-white font-bold text-[10px] shrink-0">NR</span>
                            <div>
                                <div class="text-[13px] font-semibold">{{ $s('company.name') }}</div>
                                <div class="text-[#8a929b] max-w-60 mt-0.5">{{ $s('company.address') }}</div>
                                @if ($s('company.ntn'))<div class="text-[#8a929b]">NTN {{ $s('company.ntn') }}</div>@endif
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[14px] font-bold text-[#0e6b69] tracking-[.06em] uppercase">{{ $kind }}</div>
                            <div class="font-mono text-[#566069] mt-0.5">{{ $number }}@if ($isQuote) · Rev {{ $doc->revision }}@endif</div>
                            <div class="text-[#8a929b]" x-text="d(issue) + (until ? ' · {{ $isQuote ? 'valid until' : 'due' }} ' + d(until) : '')"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-6 py-4">
                        <div><div class="text-[8.5px] font-semibold uppercase tracking-[.06em] text-[#8a929b]">Prepared for</div><div class="font-semibold mt-0.5" x-text="projects[project]?.client || '—'"></div><div class="text-[#8a929b]" x-text="projects[project]?.address || ''"></div></div>
                        <div><div class="text-[8.5px] font-semibold uppercase tracking-[.06em] text-[#8a929b]">Project</div><div class="font-semibold mt-0.5" x-text="projects[project]?.name || '—'"></div><div class="text-[#8a929b]" x-text="projects[project]?.site || ''"></div></div>
                    </div>
                    @if ($isQuote)<div class="font-semibold mb-1.5" x-text="title"></div>@endif
                    <table class="w-full">
                        <thead><tr class="border-b border-[#14171a] text-left"><th class="py-1.5 w-5">#</th><th class="py-1.5">Description</th><th class="py-1.5 text-right w-8">Qty</th><th class="py-1.5 pl-2 w-14">Unit</th><th class="py-1.5 text-right w-16">Rate</th><th class="py-1.5 text-right w-20">Amount</th></tr></thead>
                        <tbody>
                            <template x-for="(item, i) in items.filter(x => x.description)" :key="i">
                                <tr class="border-b border-[#eff1f3] align-top"><td class="py-1.5 text-[#8a929b]" x-text="i + 1"></td><td class="py-1.5 pr-2" x-text="item.description"></td><td class="py-1.5 text-right" x-text="num(item.quantity)"></td><td class="py-1.5 pl-2 text-[#8a929b]" x-text="item.unit"></td><td class="py-1.5 text-right" x-text="num(item.rate)"></td><td class="py-1.5 text-right" x-text="num(amount(item))"></td></tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="flex justify-end mt-3">
                        <dl class="w-48 space-y-0.5">
                            <div class="flex justify-between text-[#566069]"><dt>Subtotal</dt><dd x-text="fmt(subtotal)"></dd></div>
                            <div class="flex justify-between text-[#566069]" x-show="discount > 0"><dt>Discount</dt><dd x-text="'− ' + fmt(discount)"></dd></div>
                            <div class="flex justify-between text-[#566069]" x-show="tax > 0"><dt>{{ $tax }} (<span x-text="num(taxRate)"></span>%)</dt><dd x-text="fmt(tax)"></dd></div>
                            <div class="flex justify-between border-t border-[#14171a] pt-1 mt-1 font-semibold text-[11.5px]"><dt>Total</dt><dd x-text="fmt(total)"></dd></div>
                        </dl>
                    </div>
                    <div class="flex-1"></div>
                    <div class="text-[#566069]" x-show="terms"><span class="font-semibold text-[#14171a]">Terms.</span> <span x-text="terms"></span></div>
                    <div class="flex justify-between text-[8.5px] text-[#8a929b] border-t border-[#e4e7ea] pt-2 mt-3"><span>{{ collect([$s('company.website'), $s('company.email')])->filter()->implode(' · ') }}</span><span>Page 1 of 1</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sticky footer --}}
    <div class="sticky bottom-16 lg:bottom-0 z-20 bg-surface border-t border-line">
        <div class="flex items-center justify-between gap-3 px-4 lg:px-6 py-2.5">
            <div class="text-[12.5px] text-muted inline-flex items-center gap-1.5">
                <x-icon name="cloud" class="size-4" />
                @if ($doc->exists) Last saved {{ $doc->updated_at->diffForHumans() }}@if ($isQuote && $doc->parent) · Rev {{ $doc->parent->revision }} sent {{ $doc->parent->sent_at?->format('j M') }}@endif @else Not saved yet @endif
                <span class="hidden sm:inline">· Total <span class="font-semibold text-ink tabular-nums" x-text="fmt(total)"></span></span>
            </div>
            <div class="flex gap-2">
                <button name="action" value="draft" class="btn btn-secondary">Save draft</button>
                @if (($doc->status ?? 'draft') === 'draft')
                    <button name="action" value="send" class="btn btn-primary"><x-icon name="send" /> Save &amp; mark sent</button>
                @endif
            </div>
        </div>
    </div>
</form>
