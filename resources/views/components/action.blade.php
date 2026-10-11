{{-- One-click form button for PATCH/POST/DELETE actions. --}}
@props(['url', 'method' => 'PATCH', 'confirm' => null, 'block' => false])
<form method="POST" action="{{ $url }}" class="{{ $block ? 'contents' : 'inline' }}" @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
    @csrf
    @if (strtoupper($method) !== 'POST') @method($method) @endif
    @foreach ($attributes->whereStartsWith('data-') as $key => $val)
        <input type="hidden" name="{{ substr($key, 5) }}" value="{{ $val }}">
    @endforeach
    <button type="submit" {{ $attributes->whereDoesntStartWith('data-')->merge(['class' => $attributes->has('class') ? '' : 'btn btn-secondary btn-sm']) }}>{{ $slot }}</button>
</form>
