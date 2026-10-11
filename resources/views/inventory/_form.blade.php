<form id="{{ $formId }}" method="POST" action="{{ $action }}" class="space-y-4" x-data="{ type: '{{ old('type', $item->type) }}', newCat: false }">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <input type="hidden" name="_drawer" value="item">
    <div class="grid grid-cols-2 gap-2">
        @foreach (['asset' => 'Asset', 'consumable' => 'Consumable'] as $value => $label)
            <label :class="type === '{{ $value }}' ? 'border-accent bg-accent-soft text-accent' : 'border-line'" class="flex items-center justify-center h-10 rounded-lg border text-sm font-medium cursor-pointer">
                <input type="radio" name="type" value="{{ $value }}" x-model="type" class="sr-only">{{ $label }}
            </label>
        @endforeach
    </div>
    <x-field label="Name" name="name" :value="$item->name" required placeholder="Dell Latitude 5440 / A4 paper ream" />
    <div>
        <div class="flex justify-between"><label class="label">Category</label><button type="button" class="text-xs link mb-1.5" @click="newCat = !newCat" x-text="newCat ? 'Pick existing' : '+ New category'"></button></div>
        <div x-show="!newCat"><x-field name="inventory_category_id" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->type.')'])" blank="—" :value="$item->inventory_category_id" /></div>
        <div x-show="newCat" x-cloak><x-field name="new_category" placeholder="e.g. IT equipment" /></div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <x-field label="Asset tag / SKU" name="asset_tag" :value="$item->asset_tag" placeholder="NR-LT-007" />
        <x-field label="Location" name="location" :value="$item->location" placeholder="Main office" />
        @unless ($item->exists)
            <x-field label="Quantity" name="quantity" type="number" step="0.01" min="0" :value="$item->quantity ?: 1" />
        @endunless
        <x-field label="Unit" name="unit" :value="$item->unit" placeholder="pcs, ream, box" />
        <div x-show="type === 'consumable'"><x-field label="Reorder at" name="min_quantity" type="number" step="0.01" min="0" :value="$item->min_quantity" /></div>
        <x-field label="Unit cost (PKR)" name="unit_cost" type="number" step="0.01" min="0" :value="$item->unit_cost" />
    </div>
    <div x-show="type === 'asset'" class="grid grid-cols-2 gap-4">
        <x-field label="Assigned to" name="assigned_employee_id" :options="$employees->pluck('full_name', 'id')" blank="In office" :value="$item->assigned_employee_id" />
        <x-field label="Condition" name="condition" :options="\App\Models\InventoryItem::CONDITIONS" :value="$item->condition" />
        <x-field label="Purchase date" name="purchase_date" type="date" :value="$item->purchase_date" />
        <x-field label="Warranty until" name="warranty_until" type="date" :value="$item->warranty_until" />
    </div>
    <x-field label="Notes" name="notes" type="textarea" rows="3" :value="$item->notes" />
</form>
