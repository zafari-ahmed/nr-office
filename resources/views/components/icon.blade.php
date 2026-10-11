@props(['name'])
<i data-lucide="{{ $name }}" {{ $attributes->merge(['class' => 'size-4 shrink-0']) }}></i>
