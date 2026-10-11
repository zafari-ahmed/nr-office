<x-layouts.app title="Search">
    <x-page-header title="Search" :subtitle="$q ? 'Results for “'.$q.'”' : 'Type at least 2 characters'" />
    <form class="mb-6"><input name="q" value="{{ $q }}" autofocus placeholder="Search…" class="input max-w-lg"></form>
    @forelse ($results as $group => $rows)
        <div class="card mb-4">
            <div class="px-5 pt-4 pb-2 section-title">{{ $group }}</div>
            @foreach ($rows as $r)
                <a href="{{ $r['url'] }}" class="flex justify-between gap-3 px-5 py-3 border-t border-line hover:bg-surface-2"><span class="font-medium">{{ $r['title'] }}</span><span class="text-muted text-[13px]">{{ $r['meta'] }}</span></a>
            @endforeach
        </div>
    @empty
        @if ($q)<div class="card"><x-empty icon="search" title="Nothing found" /></div>@endif
    @endforelse
</x-layouts.app>
