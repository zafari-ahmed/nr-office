@props(['name' => '', 'src' => null, 'size' => 'size-9 text-xs'])
@php
    $initials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    // Each person gets a stable soft tint from their name, as in the design.
    $hue = crc32($name) % 360;
@endphp
<span {{ $attributes->merge(['class' => 'avatar '.$size]) }} title="{{ $name }}" style="background: oklch(0.66 0.09 {{ $hue }} / 0.2); color: oklch(0.55 0.11 {{ $hue }})">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="size-full object-cover">
    @else
        {{ $initials }}
    @endif
</span>
