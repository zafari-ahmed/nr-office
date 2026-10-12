@props(['label' => null, 'name', 'value' => null, 'required' => false])
{{-- Calendar-style month picker: pick a year with the arrows, then a month. Submits "YYYY-MM".
     Works with x-model on the parent (x-modelable), e.g. <x-month-picker name="payroll_month" x-model="month" />. --}}
@php
    $current = old($name, $value);
    $current = $current instanceof \DateTimeInterface ? $current->format('Y-m') : ($current ? substr($current, 0, 7) : '');
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label class="label" for="f-{{ $name }}">{{ $label }}@if ($required)<span class="text-red-fg"> *</span>@endif</label>@endif
    <div class="relative" {{ $attributes->whereStartsWith('x-model') }} x-modelable="value"
         x-data="{ value: @js($current), open: false, year: null,
                   months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                   get y() { return this.value ? Number(this.value.slice(0, 4)) : null },
                   get m() { return this.value ? Number(this.value.slice(5, 7)) : null },
                   get text() { return this.value ? new Date(this.y, this.m - 1, 1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) : 'Choose month…' },
                   toggle() { this.year = this.y || new Date().getFullYear(); this.open = ! this.open },
                   pick(i) { this.value = this.year + '-' + String(i + 1).padStart(2, '0'); this.open = false },
                   isNow(i) { const d = new Date(); return d.getFullYear() === this.year && d.getMonth() === i } }"
         @keydown.escape.stop="open = false">
        <input type="hidden" name="{{ $name }}" :value="value">
        <button type="button" id="f-{{ $name }}" class="input flex items-center justify-between gap-2 text-left" @click="toggle()" :aria-expanded="open">
            <span x-text="text" :class="value ? '' : 'text-muted'"></span>
            <x-icon name="calendar" class="text-muted" />
        </button>
        {{-- Native validation can't see a hidden input, so a visually hidden required field stands in. --}}
        @if ($required)<input tabindex="-1" class="sr-only" :value="value" required aria-hidden="true" @invalid="toggle()">@endif
        <div x-show="open" x-cloak x-transition.opacity @click.outside="open = false"
             class="absolute left-0 z-40 mt-1 w-64 rounded-card border border-line bg-surface p-3 shadow-pop">
            <div class="flex items-center justify-between mb-2">
                <button type="button" class="btn btn-ghost btn-sm !px-2" @click="year--" aria-label="Previous year"><x-icon name="chevron-left" /></button>
                <span class="font-semibold tabular-nums" x-text="year"></span>
                <button type="button" class="btn btn-ghost btn-sm !px-2" @click="year++" aria-label="Next year"><x-icon name="chevron-right" /></button>
            </div>
            <div class="grid grid-cols-3 gap-1.5">
                <template x-for="(label, i) in months" :key="i">
                    <button type="button" class="h-9 rounded-md text-[13px] font-medium transition"
                            :class="year === y && i + 1 === m ? 'bg-accent text-on-accent' : (isNow(i) ? 'border border-accent text-accent hover:bg-surface-2' : 'text-ink hover:bg-surface-2')"
                            @click="pick(i)" x-text="label"></button>
                </template>
            </div>
        </div>
    </div>
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
