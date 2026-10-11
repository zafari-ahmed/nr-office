@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 px-4 py-3 text-sm text-muted">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50"><x-icon name="chevron-left" /> Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm"><x-icon name="chevron-left" /> Prev</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm">Next <x-icon name="chevron-right" /></a>
            @else
                <span class="btn btn-secondary btn-sm opacity-50">Next <x-icon name="chevron-right" /></span>
            @endif
        </div>
    </nav>
@endif
