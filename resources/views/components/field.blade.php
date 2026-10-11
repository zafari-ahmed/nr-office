@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'options' => null, 'placeholder' => null, 'blank' => null])
@php $dotted = str_replace(['[', ']'], ['.', ''], $name); @endphp
@php $current = old($dotted, $value); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label class="label" for="f-{{ $dotted }}">{{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif</label>@endif
    @if ($options !== null)
        <select id="f-{{ $dotted }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except('class') }}>
            @if ($blank !== null)<option value="">{{ $blank }}</option>@endif
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="f-{{ $dotted }}" name="{{ $name }}" class="input" placeholder="{{ $placeholder }}" @required($required) {{ $attributes->except('class') }}>{{ $current }}</textarea>
    @else
        <input id="f-{{ $dotted }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current }}" class="input" placeholder="{{ $placeholder }}" @required($required) {{ $attributes->except('class') }}>
    @endif
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($dotted)<p class="error">{{ $message }}</p>@enderror
</div>
