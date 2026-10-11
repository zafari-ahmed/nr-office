<x-layouts.app title="Inventory">
    <x-page-header title="Inventory" subtitle="Office assets and consumables, who has what, and what's running low.">
        <x-slot:actions>
            <button class="btn btn-primary" @click="$store.drawer.open('item')"><x-icon name="plus" /> Add item</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat label="Assets" :value="$counts['asset']" icon="laptop" :href="route('inventory.index', ['tab' => 'asset'])" />
        <x-stat label="Consumables" :value="$counts['consumable']" icon="boxes" :href="route('inventory.index', ['tab' => 'consumable'])" />
        <x-stat label="Low stock" :value="$counts['low']" icon="alert-triangle" :href="route('inventory.index', ['tab' => 'consumable', 'low' => 1])" hint="at or below reorder level" />
        <x-stat label="Stock value" :value="'PKR '.money_short($counts['value'])" icon="circle-dollar-sign" />
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <nav class="tabs border-0">
            <a href="{{ route('inventory.index', ['tab' => 'asset']) }}" @class(['tab', 'tab-active' => $tab === 'asset'])>Assets</a>
            <a href="{{ route('inventory.index', ['tab' => 'consumable']) }}" @class(['tab', 'tab-active' => $tab === 'consumable'])>Consumables</a>
        </nav>
        <form class="flex gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search name or tag" class="input !h-9 !w-56">
            <select name="category" class="input !h-9 !w-44" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories->where('type', $tab) as $c)<option value="{{ $c->id }}" @selected((string) request('category') === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                @if ($tab === 'asset')
                    <thead><tr><th>Asset</th><th>Category</th><th>Assigned to</th><th>Location</th><th>Condition</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                    @forelse ($items as $item)
                        <tr class="cursor-pointer" onclick="location='{{ route('inventory.show', $item) }}'">
                            <td><div class="font-medium">{{ $item->name }}</div><div class="text-xs text-muted">{{ $item->asset_tag }}</div></td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td>@if ($item->assignee)<span class="inline-flex items-center gap-2"><x-avatar :name="$item->assignee->full_name" size="size-6 text-[10px]" />{{ $item->assignee->full_name }}</span>@else<span class="text-muted">In office</span>@endif</td>
                            <td>{{ $item->location ?? '—' }}</td>
                            <td><x-pill :status="$item->condition" /></td>
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
                            <td><div class="font-medium flex items-center gap-2">{{ $item->name }} @if ($item->isLowStock())<span class="pill pill-amber !h-5">Low</span>@endif</div><div class="text-xs text-muted">{{ $item->location }}</div></td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="bar flex-1"><span class="{{ $item->isLowStock() ? 'bg-amber-500' : 'bg-accent' }}" style="width: {{ $item->stockPercent() }}%"></span></div>
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
