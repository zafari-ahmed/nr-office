@props(['name' => 'files[]', 'label' => 'Attach files', 'accept' => null, 'multiple' => true])
<div x-data="dropzone">
    <label class="label">{{ $label }}</label>
    <label @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="drop($event)"
           :class="over ? 'border-accent bg-accent-soft' : 'border-line'"
           class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-6 text-center cursor-pointer transition hover:border-accent/60">
        <x-icon name="file-up" class="size-5 text-accent" />
        <span class="text-sm"><span class="font-medium text-accent">Click to upload</span> or drag and drop</span>
        <span class="text-xs text-muted">PDF, images, Word or Excel up to 20 MB</span>
        <input x-ref="input" type="file" name="{{ $name }}" class="sr-only" @if ($multiple) multiple @endif @if ($accept) accept="{{ $accept }}" @endif @change="pick($event)">
    </label>
    <ul class="mt-2 space-y-1" x-show="files.length">
        <template x-for="f in files" :key="f.name">
            <li class="flex items-center justify-between text-xs rounded-md bg-surface-2 px-2.5 py-1.5">
                <span class="truncate" x-text="f.name"></span><span class="text-muted ml-2 shrink-0" x-text="size(f.size)"></span>
            </li>
        </template>
    </ul>
    @error(str_replace('[]', '', $name).'.*')<p class="error">{{ $message }}</p>@enderror
</div>
