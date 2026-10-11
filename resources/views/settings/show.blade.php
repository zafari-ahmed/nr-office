<x-layouts.app title="Settings">
    <x-page-header title="Settings" :subtitle="$sections[$section][0]" />
    <div class="grid lg:grid-cols-[240px_1fr] gap-6">
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
                                        <select name="color" class="input !h-9">@foreach (['teal', 'indigo', 'amber', 'rose', 'sky', 'violet', 'emerald', 'slate'] as $c)<option @selected($head->color === $c)>{{ $c }}</option>@endforeach</select>
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
                    <x-field label="Colour" name="color" :options="array_combine($c = ['teal', 'indigo', 'amber', 'rose', 'sky', 'violet', 'emerald', 'slate'], $c)" value="sky" />
                    <x-field label="Monthly budget" name="monthly_budget" type="number" min="0" />
                    <button class="btn btn-primary"><x-icon name="plus" /> Add</button>
                </form>
            @else
                <form method="POST" action="{{ route('settings.update', $section) }}" class="card card-pad space-y-5 max-w-2xl">
                    @csrf @method('PUT')
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
                        @case('rotation')
                            <p class="text-[13px] text-muted -mt-1">These rules drive the return-date calculation and the daily reminders.</p>
                            <div class="grid sm:grid-cols-3 gap-4">
                                <x-field label="Default days on site" name="rotation[default_stay_days]" type="number" min="1" :value="\App\Models\Setting::get('rotation.default_stay_days')" required />
                                <x-field label="Remind N days before return" name="rotation[remind_days_before]" type="number" min="0" :value="\App\Models\Setting::get('rotation.remind_days_before')" required />
                                <x-field label="Overdue reminder every (days)" name="rotation[overdue_reminder_every_days]" type="number" min="1" :value="\App\Models\Setting::get('rotation.overdue_reminder_every_days')" required />
                            </div>
                            <div>
                                <label class="label">Who gets notified</label>
                                @php $notify = explode(',', \App\Models\Setting::get('rotation.notify_roles')); @endphp
                                <div class="flex flex-wrap gap-4">
                                    @foreach ($roles as $k => $l)
                                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rotation[notify_roles][]" value="{{ $k }}" class="checkbox" @checked(in_array($k, $notify))> {{ $l }}</label>
                                    @endforeach
                                </div>
                                <p class="hint">The staff member on site is always notified too, if they have a login.</p>
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
                    <div class="flex justify-end pt-2"><button class="btn btn-primary"><x-icon name="check" /> Save</button></div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
