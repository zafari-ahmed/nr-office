@props(['title', 'subtitle' => null, 'eyebrow' => null])
<div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between mb-5">
    <div class="min-w-0">
        @if ($eyebrow)<div class="mb-1 text-[13px] text-muted">{{ $eyebrow }}</div>@endif
        @isset($crumbs)<div class="mb-2.5 flex items-center gap-1.5 text-[13px] text-muted">{{ $crumbs }}</div>@endisset
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="page-title">{{ $title }}</h1>
            @isset($badges){{ $badges }}@endisset
        </div>
        @if ($subtitle)<p class="text-ink-2 text-[13.5px] mt-1">{{ $subtitle }}</p>@endif
        @isset($meta)<div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[13.5px] text-ink-2">{{ $meta }}</div>@endisset
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
