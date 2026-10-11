@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => null, 'trend' => null, 'href' => '#'])
@php
    $toneClass = match ($tone) {
        'red' => 'text-red-fg', 'amber' => 'text-amber-fg', 'green' => 'text-green-fg', default => 'text-muted',
    };
@endphp
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'card block px-[18px] pt-4 pb-[18px] no-underline text-ink transition hover:border-accent/40 hover:no-underline']) }}>
    <div class="flex items-center gap-2 text-[13px] font-medium text-ink-2">
        @if ($icon)<x-icon :name="$icon" class="size-[15px] text-muted" />@endif
        {{ $label }}
    </div>
    <div class="kpi-value mt-3.5">{{ $value }}</div>
    @if ($hint || $trend !== null)
        <div @class(['mt-2.5 flex items-center gap-1 text-[12.5px] font-medium', $toneClass])>
            @if ($trend !== null)
                <span @class(['inline-flex items-center gap-0.5', 'text-red-fg' => $trend > 0, 'text-green-fg' => $trend <= 0])>
                    <x-icon :name="$trend > 0 ? 'arrow-up' : 'arrow-down'" class="size-3.5" />{{ abs($trend) }}%
                </span>
            @endif
            <span class="truncate">{{ $hint }}</span>
        </div>
    @endif
</a>
