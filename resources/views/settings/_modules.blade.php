<form method="POST" action="{{ route('settings.update', 'modules') }}" class="card overflow-hidden">
    @csrf @method('PUT')
    <div class="px-5 py-4 border-b border-line">
        <h2 class="card-title">Modules</h2>
        <p class="text-[13px] text-ink-2 mt-0.5">Switch a module off to hide it from the menu and close its pages for everyone, you included. Nothing is deleted; switch it back on and everything is where you left it.</p>
    </div>
    @foreach (\App\Support\Modules::LIST as $key => [$label, $icon, $hint])
        <div x-data="{ on: {{ \App\Support\Modules::enabled($key) ? 'true' : 'false' }} }" class="flex gap-3.5 px-5 py-4 border-b border-line-2 last:border-b-0">
            <span class="grid place-items-center size-9 rounded-lg bg-surface-2 text-ink-2 shrink-0" :class="!on && 'opacity-50'"><x-icon :name="$icon" /></span>
            <label class="flex-1 min-w-0 flex items-start justify-between gap-3 cursor-pointer">
                <span><span class="block text-[14px] font-medium">{{ $label }}</span><span class="block text-[12.5px] text-muted">{{ $hint }}</span></span>
                <input type="hidden" name="module[{{ $key }}]" :value="on ? 1 : 0">
                <span class="switch mt-0.5"><input type="checkbox" x-model="on" aria-label="{{ $label }}"><span></span></span>
            </label>
        </div>
    @endforeach
    <div class="flex justify-end px-5 py-3 bg-surface-2 border-t border-line"><button class="btn btn-primary">Save changes</button></div>
</form>
