@php

    $alerts = [
        ['invoice_overdue', 'notify.invoice_overdue_roles', 'receipt', 'red', 'Overdue invoices', 'When an invoice passes its due date without full payment.'],
        ['expense_pending', 'notify.expense_pending_roles', 'wallet', 'amber', 'Expenses waiting for approval', 'One summary each morning while expenses are pending.'],
        ['follow_up_due', 'notify.follow_up_roles', 'phone-call', 'amber', 'Follow-ups due today', 'Goes to the person assigned. Unassigned follow-ups go to the roles below.'],
        ['rotation_return', 'rotation.notify_roles', 'map-pin', 'blue', 'Site return reminders', \App\Models\Setting::int('rotation.remind_days_before').' days before someone is due back from site. The person on site is also reminded if they have a login.'],
        ['rotation_overdue', null, 'alarm-clock', 'red', 'Overdue from site', 'Repeats '.match (\App\Models\Setting::get('rotation.overdue_reminder_every_days')) { '1' => 'daily', '7' => 'weekly', default => 'every '.\App\Models\Setting::get('rotation.overdue_reminder_every_days').' days' }.' until they are marked back. Sent to the same people as return reminders.'],
        ['low_stock', 'notify.low_stock_roles', 'package', 'amber', 'Low stock', 'When consumables drop to their minimum quantity.'],
    ];
@endphp
<form method="POST" action="{{ route('settings.update', 'notifications') }}" class="card overflow-hidden">
    @csrf @method('PUT')
    <div class="px-5 py-4 border-b border-line">
        <h2 class="card-title">Notifications</h2>
        <p class="text-[13px] text-ink-2 mt-0.5">Alerts appear under the bell for the people you choose. They are checked every morning at 8:00.</p>
    </div>
    @foreach ($alerts as [$key, $rolesKey, $icon, $tone, $title, $hint])
        @php $chosen = $rolesKey ? array_filter(explode(',', (string) \App\Models\Setting::get($rolesKey))) : []; @endphp
        <div x-data="{ on: {{ \App\Models\Setting::get("notify.$key") === '1' ? 'true' : 'false' }} }" class="flex gap-3.5 px-5 py-4 border-b border-line-2 last:border-b-0">
            <span class="grid place-items-center size-9 rounded-lg bg-{{ $tone }}-bg text-{{ $tone }}-fg shrink-0"><x-icon :name="$icon" /></span>
            <div class="flex-1 min-w-0">
                <label class="flex items-start justify-between gap-3 cursor-pointer">
                    <span><span class="block text-[14px] font-medium">{{ $title }}</span><span class="block text-[12.5px] text-muted">{{ $hint }}</span></span>
                    <input type="hidden" name="notify[{{ $key }}]" :value="on ? 1 : 0">
                    <span class="switch mt-0.5"><input type="checkbox" x-model="on" aria-label="{{ $title }}"><span></span></span>
                </label>
                @if ($rolesKey)
                    <div class="flex flex-wrap items-center gap-1.5 mt-3" :class="!on && 'opacity-50'">
                        <span class="text-[12px] text-muted mr-1">Send to</span>
                        @foreach ($roles as $k => $l)
                            <label class="chip chip-toggle !h-7" title="{{ $roleHolders[$k] ?? 'Nobody has this role yet' }}">
                                <input type="checkbox" name="{{ str_replace('.', '[', $rolesKey) }}][]" value="{{ $k }}" @checked(in_array($k, $chosen))>{{ $l }}
                            </label>
                        @endforeach
                    </div>
                @endif
                @if ($key === 'low_stock')
                    <div class="flex flex-wrap items-center gap-2 mt-3" x-data="{ freq: '{{ \App\Models\Setting::get('notify.low_stock_frequency') }}' }" :class="!on && 'opacity-50'">
                        <span class="text-[12px] text-muted mr-1">How often</span>
                        <input type="hidden" name="notify[low_stock_frequency]" :value="freq">
                        <div class="seg">
                            @foreach (['weekly' => 'Mondays', 'daily' => 'Every day'] as $v => $l)
                                <button type="button" class="seg-item" :class="freq === '{{ $v }}' && 'seg-active'" @click="freq = '{{ $v }}'">{{ $l }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
    <div class="flex justify-end px-5 py-3 bg-surface-2 border-t border-line"><button class="btn btn-primary">Save changes</button></div>
</form>
