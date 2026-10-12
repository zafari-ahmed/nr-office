<x-layouts.app title="Inventory">
    <x-page-header title="Inventory" subtitle="Equipment, IT and office stock · click any item for its history">
        <x-slot:actions>
            @can('inventory.manage')<button class="btn btn-primary" @click="$store.drawer.open('item')"><x-icon name="plus" /> Add item</button>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4 border-b border-line">
        <nav class="tabs border-0">
            <a href="{{ route('inventory.index', ['tab' => 'asset']) }}" @class(['tab', 'tab-active' => $tab === 'asset'])>Assets <span class="count bg-bg2 text-muted !h-[18px] !text-[11px] font-mono font-medium">{{ $counts['asset'] }}</span></a>
            <a href="{{ route('inventory.index', ['tab' => 'consumable']) }}" @class(['tab', 'tab-active' => $tab === 'consumable'])>Consumables <span class="count bg-bg2 text-muted !h-[18px] !text-[11px] font-mono font-medium">{{ $counts['consumable'] }}</span>
                @if ($counts['low'])<span class="count bg-red-bg text-red-fg !h-[18px] !text-[11px]">{{ $counts['low'] }} low</span>@endif
            </a>
        </nav>
        <form class="flex gap-2 pb-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="relative">
                <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted size-4" />
                <input name="q" value="{{ request('q') }}" placeholder="Search name or tag" class="input !h-[30px] !w-52 pl-9 !text-[12.5px]">
            </div>
            <select name="category" class="input !h-[30px] !w-40 !text-[12.5px]" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories->where('type', $tab) as $c)<option value="{{ $c->id }}" @selected((string) request('category') === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                @if ($tab === 'asset')
                    <thead><tr><th>Asset</th><th>Category</th><th>Assigned to</th><th>Condition</th><th>Status</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                    @forelse ($items as $item)
                        <tr class="cursor-pointer" onclick="location='{{ route('inventory.show', $item) }}'">
                            @php $state = $item->assignee ? ['blue', 'Assigned'] : ($item->condition === 'damaged' ? ['amber', 'In repair'] : ($item->condition === 'disposed' ? ['gray', 'Disposed'] : ['green', 'In store'])); @endphp
                            <td><div class="font-medium">{{ $item->name }}</div><div class="font-mono text-[11.5px] text-muted mt-0.5">{{ $item->asset_tag }}</div></td>
                            <td class="text-ink-2">{{ $item->category?->name ?? '—' }}</td>
                            <td>@if ($item->assignee)<span class="inline-flex items-center gap-2"><x-avatar :name="$item->assignee->full_name" size="size-6 text-[9.5px]" />{{ $item->assignee->full_name }}</span>@else<span class="text-muted">{{ $item->location ?: 'In store' }}</span>@endif</td>
                            <td class="text-ink-2">{{ \App\Models\InventoryItem::CONDITIONS[$item->condition] ?? $item->condition }}</td>
                            <td><span class="pill pill-{{ $state[0] }}">{{ $state[1] }}</span></td>
                            <td class="text-right tabular-nums">{{ money($item->unit_cost * $item->quantity) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="laptop" title="No assets yet" text="Track laptops, printers, furniture and tools, and who they're assigned to." /></td></tr>
                    @endforelse
                    </tbody>
                @else
                    <thead><tr><th>Item</th><th>Category</th><th class="w-64">Stock</th><th>Reorder at</th><th class="text-right">Unit cost</th></tr></thead>
                    <tbody>
                    @forelse ($items as $item)
                        <tr class="cursor-pointer" onclick="location='{{ route('inventory.show', $item) }}'">
                            <td><div class="font-medium flex items-center gap-2">{{ $item->name }} @if ($item->isLowStock())<span class="pill pill-red">Low</span>@endif</div><div class="text-[12px] text-muted">{{ $item->location }}</div></td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="bar flex-1"><span class="{{ $item->isLowStock() ? 'bg-red-fg' : 'bg-accent' }}" style="width: {{ $item->stockPercent() }}%"></span></div>
                                    <span class="tabular-nums text-[13px] whitespace-nowrap">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} {{ $item->unit }}</span>
                                </div>
                            </td>
                            <td class="text-muted">{{ rtrim(rtrim(number_format($item->min_quantity, 2), '0'), '.') }} {{ $item->unit }}</td>
                            <td class="text-right tabular-nums">{{ money($item->unit_cost) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="boxes" title="No consumables yet" text="Track stationery, pantry and cleaning supplies with reorder levels." /></td></tr>
                    @endforelse
                    </tbody>
                @endif
            </table>
        </div>
        {{ $items->links() }}
    </div>

    <x-slot:drawers>
        <x-drawer name="item" title="Add inventory item">
            @include('inventory._form', ['item' => new \App\Models\InventoryItem(['type' => $tab, 'condition' => 'good', 'unit' => 'pcs']), 'formId' => 'item-form', 'action' => route('inventory.store'), 'method' => 'POST'])
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
                <button form="item-form" class="btn btn-primary"><x-icon name="check" /> Add item</button>
            </x-slot:footer>
        </x-drawer>
    </x-slot:drawers>
</x-layouts.app>
