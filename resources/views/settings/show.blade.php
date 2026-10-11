<x-layouts.app title="Settings">
    <x-page-header title="Settings" />
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6 max-w-[1120px]">
        @include('settings._nav')
        <div class="min-w-0">
            @if ($section === 'expense-heads')
                <div class="card overflow-x-auto mb-6">
                    <table class="table">
                        <thead><tr><th>Head</th><th>Colour</th><th>Monthly budget (PKR)</th><th>Active</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($heads as $head)
                            <tr>
                                <td colspan="5" class="!p-0">
                                    <form method="POST" action="{{ route('settings.expense-heads.update', $head) }}" class="grid grid-cols-[1fr_130px_180px_60px_80px] items-center gap-3 px-4 py-2">
                                        @csrf @method('PUT')
                                        <input name="name" value="{{ $head->name }}" class="input !h-9">
                                        <select name="color" class="input !h-9">@foreach (['teal', 'blue', 'amber', 'grey', 'pink'] as $c)<option @selected($head->color === $c)>{{ $c }}</option>@endforeach</select>
                                        <input name="monthly_budget" type="number" step="1" min="0" value="{{ (float) $head->monthly_budget }}" class="input !h-9">
                                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($head->is_active)>
                                        <button class="btn btn-secondary btn-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('settings.expense-heads.store') }}" class="card card-pad grid sm:grid-cols-[1fr_140px_180px_auto] items-end gap-3">
                    @csrf
                    <x-field label="New head" name="name" required placeholder="Utilities" />
                    <x-field label="Colour" name="color" :options="array_combine($c = ['teal', 'blue', 'amber', 'grey', 'pink'], $c)" value="blue" />
                    <x-field label="Monthly budget" name="monthly_budget" type="number" min="0" />
                    <button class="btn btn-primary"><x-icon name="plus" /> Add</button>
                </form>
            @elseif ($section === 'rotation')
                @php
                    $notify = explode(',', \App\Models\Setting::get('rotation.notify_roles'));
                    $roleHints = ['admin' => 'overdue alerts and returns', 'manager' => 'for their projects', 'accounts' => 'travel and allowance follow-up', 'staff' => 'office staff with a login'];
                @endphp
                <form method="POST" action="{{ route('settings.update', 'rotation') }}" class="card overflow-hidden"
                      x-data="{ stay: {{ (int) \App\Models\Setting::get('rotation.default_stay_days') }}, remind: {{ (int) \App\Models\Setting::get('rotation.remind_days_before') }}, every: '{{ \App\Models\Setting::get('rotation.overdue_reminder_every_days') }}', weekends: {{ \App\Models\Setting::get('rotation.count_weekends', '1') === '1' ? 'true' : 'false' }},
                               fmt(d) { return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) },
                               get start() { const d = new Date(); d.setHours(0,0,0,0); d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7)); return d },
                               get back() { const d = new Date(this.start); if (this.weekends) { d.setDate(d.getDate() + Number(this.stay)) } else { for (let n = Number(this.stay); n > 0;) { d.setDate(d.getDate() + 1); if (d.getDay() % 6) n-- } } return d },
                               get reminder() { const d = new Date(this.back); d.setDate(d.getDate() - Number(this.remind)); return d } }">
                    @csrf @method('PUT')
                    <div class="px-5 py-4 border-b border-line">
                        <h2 class="card-title">Rotation rules</h2>
                        <p class="text-[13px] text-ink-2 mt-0.5">Defaults used when scheduling site visits. Changes apply to new visits only.</p>
                    </div>
                    @foreach ([['stay', 'default_stay_days', 'Default days on site', 'Return date is calculated from this when scheduling.', 1, 120], ['remind', 'remind_days_before', 'Remind before return', 'So the office can plan handover and the next person.', 0, 30]] as [$model, $key, $label, $hint, $min, $max])
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line-2">
                            <div><div class="text-[14px] font-medium">{{ $label }}</div><div class="text-[12.5px] text-muted">{{ $hint }}</div></div>
                            <div class="inline-flex items-center h-[34px] rounded-lg border border-line bg-surface">
                                <button type="button" class="grid place-items-center w-9 h-full text-ink-2 hover:text-ink border-r border-line" @click="{{ $model }} = Math.max({{ $min }}, {{ $model }} - 1)" aria-label="Decrease"><x-icon name="minus" class="size-4" /></button>
                                <input type="hidden" name="rotation[{{ $key }}]" :value="{{ $model }}">
                                <span class="w-[76px] text-center text-[13.5px] font-semibold tabular-nums" x-text="{{ $model }} + ({{ $model }} == 1 ? ' day' : ' days')"></span>
                                <button type="button" class="grid place-items-center w-9 h-full text-ink-2 hover:text-ink border-l border-line" @click="{{ $model }} = Math.min({{ $max }}, {{ $model }} + 1)" aria-label="Increase"><x-icon name="plus" class="size-4" /></button>
                            </div>
                        </div>
                    @endforeach
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line-2">
                        <div><div class="text-[14px] font-medium">Overdue reminders</div><div class="text-[12.5px] text-muted">How often we nudge once someone is past their return date.</div></div>
                        <input type="hidden" name="rotation[overdue_reminder_every_days]" :value="every">
                        <div class="seg">
                            @foreach (['1' => 'Daily', '2' => 'Every 2 days', '7' => 'Weekly'] as $v => $l)
                                <button type="button" class="seg-item" :class="every === '{{ $v }}' && 'seg-active'" @click="every = '{{ $v }}'">{{ $l }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="px-5 py-4 border-b border-line-2">
                        <div class="text-[14px] font-medium">Who gets notified</div>
                        <div class="text-[12.5px] text-muted">For return reminders and overdue alerts. The staff member on site is always reminded if they have a login.</div>
                        <div class="mt-3 rounded-[10px] border border-line divide-y divide-line-2">
                            @foreach ($roles as $k => $l)
                                <label class="flex items-center justify-between gap-3 px-3.5 py-3 cursor-pointer">
                                    <span><span class="block text-[13.5px] font-medium">{{ $l }}</span><span class="block text-[12px] text-muted">{{ $roleHolders[$k] ?? 'Nobody yet' }} · {{ $roleHints[$k] ?? '' }}</span></span>
                                    <span class="switch"><input type="checkbox" name="rotation[notify_roles][]" value="{{ $k }}" @checked(in_array($k, $notify))><span></span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <label class="flex items-center justify-between gap-3 px-5 py-4 cursor-pointer">
                        <span><span class="block text-[14px] font-medium">Count weekends in stay length</span><span class="block text-[12.5px] text-muted">Turn off if staff don't work on site at weekends.</span></span>
                        <input type="hidden" name="rotation[count_weekends]" :value="weekends ? 1 : 0">
                        <span class="switch"><input type="checkbox" x-model="weekends"><span></span></span>
                    </label>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 bg-surface-2 border-t border-line">
                        <span class="inline-flex items-center gap-2 text-[13px] text-ink-2"><x-icon name="info" class="size-4 text-muted" /><span x-text="`A visit starting ${fmt(start)} returns ${fmt(back)}. Reminder goes out ${fmt(reminder)}.`"></span></span>
                        <button class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            @else
                <form method="POST" action="{{ route('settings.update', $section) }}" class="card overflow-hidden">
                    @csrf @method('PUT')
                    <div class="px-5 py-4 border-b border-line"><h2 class="card-title">{{ $sections[$section][0] }}</h2></div>
                    <div class="p-5 space-y-5">
                    @switch($section)
                        @case('company')
                            <x-field label="Company name" name="company[name]" :value="\App\Models\Setting::get('company.name')" required />
                            <x-field label="Tagline" name="company[tagline]" :value="\App\Models\Setting::get('company.tagline')" />
                            <x-field label="Address" name="company[address]" type="textarea" rows="2" :value="\App\Models\Setting::get('company.address')" />
                            <div class="grid sm:grid-cols-3 gap-4">
                                <x-field label="Phone" name="company[phone]" :value="\App\Models\Setting::get('company.phone')" />
                                <x-field label="Email" name="company[email]" type="email" :value="\App\Models\Setting::get('company.email')" />
                                <x-field label="NTN" name="company[ntn]" :value="\App\Models\Setting::get('company.ntn')" />
                            </div>
                            @break
                        @case('numbering')
                            <div class="grid sm:grid-cols-2 gap-4">
                                <x-field label="Project prefix" name="numbering[project_prefix]" :value="\App\Models\Setting::get('numbering.project_prefix')" hint="NR → NR-2026-014" />
                                <x-field label="Quotation prefix" name="numbering[quotation_prefix]" :value="\App\Models\Setting::get('numbering.quotation_prefix')" hint="QT → QT-2026-0007" />
                                <x-field label="Invoice prefix" name="numbering[invoice_prefix]" :value="\App\Models\Setting::get('numbering.invoice_prefix')" hint="INV → INV-2026-0012" />
                                <x-field label="Employee prefix" name="numbering[employee_prefix]" :value="\App\Models\Setting::get('numbering.employee_prefix')" hint="EMP → EMP-008" />
                            </div>
                            @break
                        @case('finance')
                            <div class="grid sm:grid-cols-2 gap-4">
                                <x-field label="Currency" name="finance[currency]" :value="\App\Models\Setting::get('finance.currency')" />
                                <x-field label="Payment terms (days)" name="finance[payment_terms_days]" type="number" min="0" :value="\App\Models\Setting::get('finance.payment_terms_days')" />
                                <x-field label="Tax label" name="finance[tax_label]" :value="\App\Models\Setting::get('finance.tax_label')" />
                                <x-field label="Default tax rate (%)" name="finance[tax_rate]" type="number" step="0.01" min="0" :value="\App\Models\Setting::get('finance.tax_rate')" />
                            </div>
                            <x-field label="Default quotation terms" name="finance[quotation_terms]" type="textarea" rows="4" :value="\App\Models\Setting::get('finance.quotation_terms')" />
                            @break
                    @endswitch
                    </div>
                    <div class="flex justify-end px-5 py-3 bg-surface-2 border-t border-line"><button class="btn btn-primary">Save changes</button></div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
