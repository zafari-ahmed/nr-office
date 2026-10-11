<x-layouts.app :title="$item->name">
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('inventory.index', ['tab' => $item->type]) }}" class="text-[13px] text-muted hover:text-ink inline-flex items-center gap-1"><x-icon name="chevron-left" class="size-3.5" /> Inventory</a>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <h1 class="text-[22px] font-semibold">{{ $item->name }}</h1>
                <span class="pill pill-gray">{{ ucfirst($item->type) }}</span>
                @if ($item->isLowStock())<span class="pill pill-amber">Low stock</span>@endif
                @if ($item->type === 'asset')<x-pill :status="$item->condition" />@endif
            </div>
            <p class="text-muted mt-1">{{ $item->category?->name }}{{ $item->asset_tag ? ' · '.$item->asset_tag : '' }}{{ $item->location ? ' · '.$item->location : '' }}</p>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-secondary" @click="$store.drawer.open('edit-item')"><x-icon name="pencil" /> Edit</button>
            <button class="btn btn-primary" @click="$store.drawer.open('movement')"><x-icon name="arrow-right-left" /> {{ $item->type === 'asset' ? 'Assign / move' : 'Stock in / out' }}</button>
        </div>
    </div>

    <div class="grid lg:grid-cols-[1fr_300px] gap-6">
        <div class="card">
            <div class="px-5 pt-5 pb-3 card-title">History</div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Movement</th><th>Qty</th><th>Balance</th><th>Person / project</th><th>By</th></tr></thead>
                    <tbody>
                    @foreach ($item->transactions as $t)
                        <tr>
                            <td class="whitespace-nowrap">{{ $t->transaction_date->format('d M Y') }}</td>
                            <td><div>{{ \App\Models\InventoryTransaction::TYPES[$t->type] ?? $t->type }}</div>@if ($t->notes)<div class="text-xs text-muted">{{ $t->notes }}</div>@endif</td>
                            <td class="tabular-nums">{{ in_array($t->type, ['assign', 'unassign']) ? '—' : rtrim(rtrim(number_format($t->quantity, 2), '0'), '.') }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format($t->balance_after, 2), '0'), '.') }}</td>
                            <td class="text-[13px]">{{ $t->employee?->full_name }}{{ $t->employee && $t->project ? ' · ' : '' }}{{ $t->project?->name }}</td>
                            <td class="text-muted text-[13px]">{{ $t->creator?->name }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <aside class="card card-pad text-[13px] space-y-2 self-start">
            <div class="section-title mb-1">Details</div>
            <div class="flex justify-between"><span class="text-muted">In stock</span><span class="font-medium">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} {{ $item->unit }}</span></div>
            @if ($item->type === 'consumable')<div class="flex justify-between"><span class="text-muted">Reorder at</span><span>{{ rtrim(rtrim(number_format($item->min_quantity, 2), '0'), '.') }} {{ $item->unit }}</span></div>@endif
            <div class="flex justify-between"><span class="text-muted">Unit cost</span><span>{{ money($item->unit_cost) }}</span></div>
            @if ($item->type === 'asset')
                <div class="flex justify-between"><span class="text-muted">Assigned to</span><span>{{ $item->assignee?->full_name ?? 'In office' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Purchased</span><span>{{ $item->purchase_date?->format('d M Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Warranty</span><span @class(['text-red-600' => $item->warranty_until?->isPast()])>{{ $item->warranty_until?->format('d M Y') ?? '—' }}</span></div>
            @endif
            @if ($item->notes)<p class="pt-2 border-t border-line text-muted whitespace-pre-line">{{ $item->notes }}</p>@endif
            <x-action :url="route('inventory.destroy', $item)" method="DELETE" confirm="Remove this item?" class="btn btn-ghost btn-sm w-full mt-2 hover:text-red-600"><x-icon name="trash-2" /> Remove item</x-action>
        </aside>
    </div>

    <x-slot:drawers>
        <x-drawer name="edit-item" title="Edit item">
            @include('inventory._form', ['formId' => 'edit-item-form', 'action' => route('inventory.update', $item), 'method' => 'PUT'])
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
                <button form="edit-item-form" class="btn btn-primary">Save</button>
            </x-slot:footer>
        </x-drawer>
        <x-drawer name="movement" :title="$item->type === 'asset' ? 'Assign or move asset' : 'Stock movement'">
            <form id="movement-form" method="POST" action="{{ route('inventory.transaction', $item) }}" class="space-y-4" x-data="{ type: '{{ $item->type === 'asset' ? 'assign' : 'issue' }}' }">
                @csrf
                <input type="hidden" name="_drawer" value="movement">
                @php $types = $item->type === 'asset' ? collect(\App\Models\InventoryTransaction::TYPES)->only('assign', 'unassign', 'dispose') : collect(\App\Models\InventoryTransaction::TYPES)->only('purchase', 'issue', 'return', 'adjustment'); @endphp
                <x-field label="Movement" name="type" :options="$types" x-model="type" />
                <div x-show="!['assign', 'unassign', 'dispose'].includes(type)"><x-field label="Quantity" name="quantity" type="number" step="0.01" min="0" hint="For adjustment, enter the counted quantity" /></div>
                <x-field label="Employee" name="employee_id" :options="$employees->pluck('full_name', 'id')" blank="—" />
                <x-field label="Project" name="project_id" :options="$projects->pluck('name', 'id')" blank="—" />
                <x-field label="Date" name="transaction_date" type="date" :value="today()->format('Y-m-d')" required />
                <x-field label="Notes" name="notes" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
                <button form="movement-form" class="btn btn-primary">Record</button>
            </x-slot:footer>
        </x-drawer>
    </x-slot:drawers>
</x-layouts.app>
