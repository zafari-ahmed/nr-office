@props(['name' => '', 'src' => null, 'size' => 'size-9 text-xs'])
@php $initials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode(''); @endphp
<span {{ $attributes->merge(['class' => 'avatar '.$size]) }} title="{{ $name }}">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="size-full object-cover">
    @else
        {{ $initials }}
    @endif
</span>
