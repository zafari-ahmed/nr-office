@props(['label', 'value', 'icon' => null, 'hint' => null, 'trend' => null, 'href' => '#'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'card card-pad block transition hover:border-accent/40']) }}>
    <div class="flex items-center justify-between">
        <span class="text-[13px] text-muted">{{ $label }}</span>
        @if ($icon)<span class="grid place-items-center size-8 rounded-lg bg-accent-soft text-accent"><x-icon :name="$icon" /></span>@endif
    </div>
    <div class="kpi-value mt-2">{{ $value }}</div>
    @if ($hint || $trend !== null)
        <div class="mt-1 flex items-center gap-1.5 text-xs text-muted">
            @if ($trend !== null)
                <span @class(['inline-flex items-center gap-0.5 font-medium', 'text-red-600' => $trend > 0, 'text-emerald-600' => $trend <= 0])>
                    <x-icon :name="$trend > 0 ? 'arrow-up-right' : 'arrow-down-right'" class="size-3.5" />{{ abs($trend) }}%
                </span>
            @endif
            {{ $hint }}
        </div>
    @endif
</a>
