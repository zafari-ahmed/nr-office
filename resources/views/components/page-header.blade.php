@props(['title', 'subtitle' => null])
<div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between mb-6">
    <div class="min-w-0">
        @isset($eyebrow)<div class="mb-1 text-[13px] text-muted">{{ $eyebrow }}</div>@endisset
        <h1 class="text-[22px] font-semibold text-ink leading-tight">{{ $title }}</h1>
        @if ($subtitle)<p class="text-muted mt-1">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
