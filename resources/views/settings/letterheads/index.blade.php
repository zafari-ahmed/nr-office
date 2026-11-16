<x-layouts.app title="Letterhead & templates">
    <x-page-header title="Settings" subtitle="Letterhead & templates" />
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6 max-w-[1120px]">
        @include('settings._nav')
        <div class="min-w-0 space-y-6">
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line">
                    <div>
                        <h2 class="card-title">Letterheads</h2>
                        <p class="text-[13px] text-ink-2 mt-0.5">Upload your letterhead as a PDF or Word file. The header and footer are printed on every page; your content goes in between.</p>
                    </div>
                    <a href="{{ route('settings.letterheads.create') }}" class="btn btn-primary btn-sm"><x-icon name="upload" /> Upload letterhead</a>
                </div>
                @forelse ($letterheads as $lh)
                    <div class="flex flex-wrap items-center gap-4 px-5 py-4 border-b border-line-2 last:border-b-0">
                        <div class="w-[150px] shrink-0 rounded-md border border-line bg-white overflow-hidden shadow-sm">
                            @if ($lh->header_path)<img src="{{ $lh->imageUrl('header') }}" alt="" class="block w-full">@else<div class="h-8 grid place-items-center text-[11px] text-muted">No header</div>@endif
                            <div class="h-6"></div>
                            @if ($lh->footer_path)<img src="{{ $lh->imageUrl('footer') }}" alt="" class="block w-full">@endif
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <div class="flex items-center gap-2"><span class="text-[14px] font-medium">{{ $lh->name }}</span>@if ($lh->is_default)<x-pill status="active" label="Default" />@endif</div>
                            <div class="text-[12.5px] text-muted mt-0.5">Header {{ $lh->header_mm }} mm · footer {{ $lh->footer_mm }} mm · side margins {{ $lh->side_mm }} mm @if ($lh->source_name) · from <a href="{{ route('settings.letterheads.source', [$lh, 'download' => 1]) }}">{{ $lh->source_name }}</a>@endif</div>
                        </div>
                        <div class="flex items-center gap-1">
                            @unless ($lh->is_default)<x-action :url="route('settings.letterheads.default', $lh)" class="btn btn-ghost btn-sm">Make default</x-action>@endunless
                            <a href="{{ route('settings.letterheads.edit', $lh) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" /> Edit</a>
                            <x-action :url="route('settings.letterheads.destroy', $lh)" method="DELETE" confirm="Remove this letterhead? Documents already printed on it can still be reprinted." class="btn btn-icon hover:text-red-fg" title="Remove"><x-icon name="trash-2" /></x-action>
                        </div>
                    </div>
                @empty
                    <x-empty icon="stamp" title="No letterhead yet" text="Upload the PDF or Word file of your letterhead (an old letter on it works too). You mark where the header ends and the footer starts." />
                @endforelse
            </div>

            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line">
                    <div>
                        <h2 class="card-title">Document templates</h2>
                        <p class="text-[13px] text-ink-2 mt-0.5">One per kind of document, such as Quotation, Purchase order or Letter. Upload an old document and its text becomes the starting point for new ones.</p>
                    </div>
                    <a href="{{ route('settings.templates.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" /> New template</a>
                </div>
                @if ($templates->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Template</th><th>Reference</th><th>Letterhead</th><th>Made from</th><th class="text-right">Documents</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($templates as $t)
                                <tr>
                                    <td class="font-medium">{{ $t->name }}</td>
                                    <td class="font-mono text-[12.5px] text-ink-2">{{ $t->ref_prefix }}-{{ now()->year }}-0001</td>
                                    <td class="text-ink-2">{{ $t->letterhead?->name ?? 'Default' }}</td>
                                    <td class="text-ink-2">@if ($t->sample_name)<a href="{{ route('settings.templates.sample', $t) }}">{{ \Illuminate\Support\Str::limit($t->sample_name, 32) }}</a>@else — @endif</td>
                                    <td class="text-right tabular-nums">{{ $t->documents_count }}</td>
                                    <td><x-pill :status="$t->is_active ? 'active' : 'cancelled'" :label="$t->is_active ? 'Active' : 'Hidden'" /></td>
                                    <td class="text-right whitespace-nowrap">
                                        <a href="{{ route('settings.templates.edit', $t) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" /> Edit</a>
                                        <x-action :url="route('settings.templates.destroy', $t)" method="DELETE" confirm="Remove this template? Documents made from it are kept." class="btn btn-icon hover:text-red-fg" title="Remove"><x-icon name="trash-2" /></x-action>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-empty icon="file-text" title="No templates yet" text="Make one for each kind of document you send, for example Quotation and Purchase order." />
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
