<x-layouts.app title="Branding">
    <x-page-header title="Settings" subtitle="Branding" />
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6 max-w-[1120px]">
        @include('settings._nav')
        <div class="space-y-4 min-w-0">
            <div class="card card-pad">
                <h2 class="card-title">Office assets</h2>
                <p class="text-[13px] text-ink-2 mt-0.5">Upload your logo, sidebar icon and favicon. Only Super admins can change these. PNG, JPG, WebP or SVG up to 2 MB.</p>
            </div>
            @foreach (\App\Support\Branding::ASSETS as $asset => [$label, $hint, $default, $types])
                @php($url = \App\Support\Branding::url($asset))
                <div class="card card-pad grid sm:grid-cols-[160px_minmax(0,1fr)] gap-5 items-start">
                    <div @class(['grid place-items-center rounded-lg border border-line bg-surface-2 overflow-hidden', 'h-28' => $asset === 'logo', 'h-20' => $asset !== 'logo'])>
                        @if ($url)
                            <img src="{{ $url }}" alt="{{ $label }}" @class(['object-contain', 'max-h-24 max-w-[140px]' => $asset === 'logo', 'size-12' => $asset !== 'logo'])>
                        @else
                            <x-brand-mark class="size-12 rounded-lg" badge="bg-accent text-on-accent font-bold text-sm" />
                        @endif
                    </div>
                    <div class="space-y-3 min-w-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold">{{ $label }}</h3>
                                <x-pill :status="\App\Support\Branding::isCustom($asset) ? 'active' : 'draft'" :label="\App\Support\Branding::isCustom($asset) ? 'Uploaded' : ($url ? 'Default' : 'Not set')" />
                            </div>
                            <p class="text-[13px] text-muted">{{ $hint }}</p>
                        </div>
                        <form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="file" name="{{ $asset }}" accept="{{ collect(explode(',', $types))->map(fn ($t) => '.'.$t)->implode(',') }}" required class="text-[13px] min-w-0 file:mr-3 file:rounded-md file:border file:border-line file:bg-surface file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:cursor-pointer">
                            <button class="btn btn-primary btn-sm"><x-icon name="upload" /> Upload</button>
                        </form>
                        @error($asset)<p class="text-[12.5px] text-red-fg">{{ $message }}</p>@enderror
                        @if (\App\Support\Branding::isCustom($asset))
                            <x-action :url="route('settings.branding.destroy', $asset)" method="DELETE" confirm="Go back to the default {{ strtolower($label) }}?" class="btn btn-ghost btn-sm"><x-icon name="rotate-ccw" /> Use default</x-action>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
