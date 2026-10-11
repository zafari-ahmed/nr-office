@props(['name', 'title', 'subtitle' => null, 'width' => 'max-w-lg'])
<div x-data x-cloak x-show="$store.drawer.name === '{{ $name }}'" class="fixed inset-0 z-50" @keydown.escape.window="$store.drawer.close()">
    <div x-show="$store.drawer.name === '{{ $name }}'" x-transition.opacity class="absolute inset-0 bg-gray-900/30 backdrop-blur-[1px]" @click="$store.drawer.close()"></div>
    <aside x-show="$store.drawer.name === '{{ $name }}'"
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="absolute right-0 top-0 h-full w-full {{ $width }} bg-surface border-l border-line shadow-2xl flex flex-col">
        <header class="flex items-start justify-between gap-4 px-6 py-4 border-b border-line">
            <div>
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                @if ($subtitle)<p class="text-xs text-muted mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            <button type="button" class="btn btn-icon -mr-2" @click="$store.drawer.close()"><x-icon name="x" /></button>
        </header>
        <div class="flex-1 overflow-y-auto px-6 py-5">{{ $slot }}</div>
        @isset($footer)
            <footer class="px-6 py-3 border-t border-line bg-surface-2/50 flex items-center justify-end gap-2">{{ $footer }}</footer>
        @endisset
    </aside>
</div>
