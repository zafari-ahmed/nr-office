<x-layouts.app :title="$letterhead->exists ? 'Edit letterhead' : 'Upload letterhead'">
    <x-page-header title="Settings" :subtitle="$letterhead->exists ? 'Edit letterhead' : 'Upload letterhead'" />
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6 max-w-[1120px]">
        @include('settings._nav')
        <form method="POST" enctype="multipart/form-data" action="{{ $letterhead->exists ? route('settings.letterheads.update', $letterhead) : route('settings.letterheads.store') }}"
              x-data="letterheadCutter({ sourceUrl: @js($letterhead->source_path ? route('settings.letterheads.source', $letterhead) : null), sourceName: @js($letterhead->source_name ?? ''), headerMm: {{ old('header_mm', $letterhead->header_mm) }}, footerMm: {{ old('footer_mm', $letterhead->footer_mm) }}, sideMm: {{ old('side_mm', $letterhead->side_mm) }}, hasImages: {{ $letterhead->header_path || $letterhead->footer_path ? 'true' : 'false' }} })"
              @submit.prevent="submit($el)" class="min-w-0 card overflow-hidden">
            @csrf
            @if ($letterhead->exists) @method('PUT') @endif
            <input type="file" name="header" x-ref="header" class="hidden">
            <input type="file" name="footer" x-ref="footer" class="hidden">
            <input type="hidden" name="clear_header" x-ref="clearHeader" value="0">
            <input type="hidden" name="clear_footer" x-ref="clearFooter" value="0">
            <input type="hidden" name="header_mm" :value="headerMm">
            <input type="hidden" name="footer_mm" :value="footerMm">
            <input type="hidden" name="side_mm" :value="sideMm">

            <div class="px-5 py-4 border-b border-line">
                <h2 class="card-title">{{ $letterhead->exists ? $letterhead->name : 'Upload letterhead' }}</h2>
                <p class="text-[13px] text-ink-2 mt-0.5">Use the PDF or Word file of your letterhead. An old letter printed on it is fine: only the header and footer are kept.</p>
            </div>

            <div class="grid md:grid-cols-[minmax(0,1fr)_380px] gap-6 p-5">
                <div class="space-y-5 min-w-0">
                    <x-field label="Name" name="name" :value="$letterhead->name ?: 'NR Associates letterhead'" required />

                    <div>
                        <label class="label">Letterhead file @unless ($letterhead->exists)<span class="text-red-fg"> *</span>@endunless</label>
                        <label class="flex items-center gap-3 rounded-lg border border-dashed border-line bg-surface-2 px-4 py-3.5 cursor-pointer hover:border-accent/50">
                            <x-icon name="upload" class="text-ink-2" />
                            <span class="flex-1 min-w-0 text-[13.5px]">
                                <span class="block font-medium truncate" x-text="fileName || 'Choose a PDF, Word (.docx) or image'"></span>
                                <span class="block text-[12px] text-muted">PDF gives an exact copy. From Word you can also use File › Save As › PDF.</span>
                            </span>
                            <input type="file" name="source" accept=".pdf,.docx,.png,.jpg,.jpeg,.webp" class="sr-only" @change="pick($event)">
                        </label>
                        @error('source')<p class="error">{{ $message }}</p>@enderror
                        @error('header')<p class="error">{{ $message }}</p>@enderror
                        <p x-cloak x-show="busy" class="hint flex items-center gap-1.5"><x-icon name="loader-circle" class="!size-3.5 animate-spin" /> Reading the file…</p>
                        <p x-cloak x-show="error" class="error" x-text="error"></p>
                    </div>

                    <div class="rounded-lg border border-line divide-y divide-line-2">
                        @foreach ([['headerMm', 'useHeader', 'Header height', 'Drag the top line on the page, or set it here.'], ['footerMm', 'useFooter', 'Footer height', 'Drag the bottom line on the page, or set it here.'], ['sideMm', null, 'Left and right margin', 'Space between the page edge and your text.']] as [$model, $toggle, $label, $hint])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <div class="text-[13.5px] font-medium flex items-center gap-2">
                                        @if ($toggle)<input type="checkbox" class="checkbox" x-model="{{ $toggle }}" @change="changed = true" aria-label="Print {{ strtolower($label) }}">@endif
                                        {{ $label }}
                                    </div>
                                    <div class="text-[12px] text-muted">{{ $hint }}</div>
                                </div>
                                <div class="inline-flex shrink-0 items-center h-[32px] rounded-lg border border-line bg-surface">
                                    <button type="button" class="grid place-items-center w-8 h-full text-ink-2 hover:text-ink border-r border-line" @click="nudge('{{ $model }}', -1)" aria-label="Less"><x-icon name="minus" class="size-4" /></button>
                                    <span class="w-[70px] text-center text-[13px] font-semibold tabular-nums" x-text="Math.round({{ $model }}) + ' mm'"></span>
                                    <button type="button" class="grid place-items-center w-8 h-full text-ink-2 hover:text-ink border-l border-line" @click="nudge('{{ $model }}', 1)" aria-label="More"><x-icon name="plus" class="size-4" /></button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[12.5px] text-muted">Tip: untick Header or Footer if your letterhead has only one of them. When printing you can also leave the letterhead off for paper that already has it printed.</p>
                </div>

                <div>
                    <div class="text-[12.5px] font-medium text-ink-2 mb-2">Page preview</div>
                    <div x-ref="page" class="relative w-full aspect-[210/297] rounded-md border border-line bg-white shadow-sm overflow-hidden select-none touch-none" :style="preview && `aspect-ratio: 210 / ${pageMm}`">
                        <template x-if="preview"><img :src="preview" alt="" class="absolute inset-0 w-full h-full" draggable="false"></template>
                        <template x-if="!preview">
                            <div class="absolute inset-0 flex flex-col">
                                @if ($letterhead->header_path)<img src="{{ $letterhead->imageUrl('header') }}" alt="" class="block w-full">@endif
                                <div class="flex-1 grid place-items-center text-[12.5px] text-muted px-6 text-center" x-text="busy ? 'Reading the file…' : 'Choose your letterhead file to see it here'"></div>
                                @if ($letterhead->footer_path)<img src="{{ $letterhead->imageUrl('footer') }}" alt="" class="block w-full">@endif
                            </div>
                        </template>
                        {{-- Content area between the header and footer --}}
                        <div class="absolute bg-accent/10 border border-dashed border-accent/50 grid place-items-center pointer-events-none"
                             :style="`top: ${pct(useHeader ? headerMm : 0)}; bottom: ${pct(useFooter ? footerMm : 0)}; left: ${sideMm / 2.1}%; right: ${sideMm / 2.1}%`">
                            <span class="rounded bg-white/90 px-2 py-1 text-[11.5px] font-medium text-accent">Your content goes here</span>
                        </div>
                        <div x-show="useHeader" class="absolute left-0 right-0 h-0 border-t-2 border-accent cursor-ns-resize" :style="`top: ${pct(headerMm)}`" @pointerdown.prevent="startDrag('header')">
                            <span class="absolute -top-3 right-1.5 rounded bg-accent px-1.5 py-0.5 text-[10.5px] font-semibold text-on-accent">Header ends</span>
                            <span class="absolute -top-3 left-0 right-0 h-6"></span>
                        </div>
                        <div x-show="useFooter" class="absolute left-0 right-0 h-0 border-t-2 border-accent cursor-ns-resize" :style="`bottom: ${pct(footerMm)}`" @pointerdown.prevent="startDrag('footer')">
                            <span class="absolute -top-3 right-1.5 rounded bg-accent px-1.5 py-0.5 text-[10.5px] font-semibold text-on-accent">Footer starts</span>
                            <span class="absolute -top-3 left-0 right-0 h-6"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 px-5 py-3 bg-surface-2 border-t border-line">
                <a href="{{ route('settings.letterheads.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary" :disabled="busy">Save letterhead</button>
            </div>
        </form>
    </div>
</x-layouts.app>
