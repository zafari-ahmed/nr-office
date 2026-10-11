@props(['status', 'label' => null, 'tone' => null])
<span {{ $attributes->merge(['class' => 'pill pill-'.($tone ?? status_tone($status))]) }}>{{ $label ?? status_label($status) }}</span>
