@props(['name'])
{{-- Rendered as inline SVG on the server so icons don't pop in after the page loads; unknown names fall back to Lucide in the browser. --}}
@if ($svg = lucide_svg($name))
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'lucide size-4 shrink-0']) }}>{!! $svg !!}</svg>
@else
<i data-lucide="{{ $name }}" {{ $attributes->merge(['class' => 'size-4 shrink-0']) }}></i>
@endif
