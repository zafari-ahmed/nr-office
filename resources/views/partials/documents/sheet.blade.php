{{-- One A4 letterhead sheet. Needs partials.documents.styles on the page. $letterhead may be null (plain paper). --}}
@php
    $lh = $letterhead ?? null;
    $vars = '--lh-head: '.($lh?->header_mm ?? 20).'mm; --lh-foot: '.($lh?->footer_mm ?? 15).'mm; --lh-side: '.($lh?->side_mm ?? 20).'mm';
@endphp
<div class="lh-sheet {{ $class ?? '' }}" style="{{ $vars }}" {{ $attrs ?? '' }}>
    <div class="lh-head">@if ($lh?->header_path)<img src="{{ $lh->imageUrl('header') }}" alt="">@endif</div>
    <table class="lh-flow">
        <thead><tr><td><div></div></td></tr></thead>
        <tfoot><tr><td><div></div></td></tr></tfoot>
        <tbody><tr><td><div class="lh-content doc-body">{!! $body !!}</div></td></tr></tbody>
    </table>
    <div class="lh-foot">@if ($lh?->footer_path)<img src="{{ $lh->imageUrl('footer') }}" alt="">@endif</div>
</div>
