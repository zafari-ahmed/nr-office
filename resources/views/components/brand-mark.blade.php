@props(['class' => 'size-10 rounded-lg', 'badge' => 'bg-accent text-on-accent font-bold text-sm'])
{{-- The uploaded sidebar icon (Settings › Branding), or the "NR" badge. --}}
@if ($iconUrl = \App\Support\Branding::url('icon'))
    <img src="{{ $iconUrl }}" alt="" class="{{ $class }} object-contain shrink-0">
@else
    <span {{ $attributes->merge(['class' => "grid place-items-center shrink-0 {$class} {$badge}"]) }}>NR</span>
@endif
