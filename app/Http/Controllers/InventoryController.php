<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Project;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'asset') === 'consumable' ? 'consumable' : 'asset';

        $items = InventoryItem::with('category', 'assignee')->where('type', $tab)
            ->when($request->category, fn ($q, $c) => $q->where('inventory_category_id', $c))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('asset_tag', 'like', "%$s%")))
            ->when($request->boolean('low'), fn ($q) => $q->lowStock())
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('inventory.index', [
            'tab' => $tab,
            'items' => $items,
            'categories' => InventoryCategory::orderBy('name')->get(),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name']),
            'counts' => [
                'asset' => InventoryItem::where('type', 'asset')->count(),
                'consumable' => InventoryItem::where('type', 'consumable')->count(),
                'low' => InventoryItem::lowStock()->count(),
                'value' => (float) InventoryItem::where('condition', '!=', 'disposed')->selectRaw('sum(quantity * unit_cost) as v')->value('v'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $item = DB::transaction(function () use ($data, $request) {
            $item = InventoryItem::create(collect($data)->except('new_category')->all());
            InventoryTransaction::create([
                'inventory_item_id' => $item->id, 'type' => 'purchase', 'quantity' => $item->quantity,
                'balance_after' => $item->quantity, 'employee_id' => $item->assigned_employee_id,
                'transaction_date' => $item->purchase_date ?? today(), 'notes' => 'Opening stock',
                'created_by' => $request->user()->id,
            ]);

            return $item;
        });
        Activity::log('created', "Inventory item added: {$item->name}", $item);

        return redirect()->route('inventory.index', ['tab' => $item->type])->with('success', 'Item added.');
    }

    public function show(InventoryItem $item)
    {
        $item->load('category', 'assignee', 'transactions.employee', 'transactions.project', 'transactions.creator');

        return view('inventory.show', [
            'item' => $item,
            'categories' => InventoryCategory::orderBy('name')->get(),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name']),
            'projects' => Project::whereIn('status', ['active', 'on_hold'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, InventoryItem $item)
    {
        $data = $this->validated($request, $item);
        $item->update(collect($data)->except('new_category', 'quantity')->all());

        return back()->with('success', 'Item updated.');
    }

    /** Stock movement: purchase/issue/return/adjust change quantity; assign/unassign change holder. */
    public function transaction(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(InventoryTransaction::TYPES))],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $qty = (float) ($data['quantity'] ?? 0);

        DB::transaction(function () use ($item, $data, $qty, $request) {
            $item->quantity = match ($data['type']) {
                'purchase', 'return' => $item->quantity + $qty,
                'issue' => max(0, $item->quantity - $qty),
                'adjustment' => $qty,
                'dispose' => $item->type === 'asset' ? 0 : max(0, $item->quantity - $qty),
                default => $item->quantity,
            };
            if ($data['type'] === 'assign') {
                $item->assigned_employee_id = $data['employee_id'] ?? null;
            }
            if ($data['type'] === 'unassign') {
                $item->assigned_employee_id = null;
            }
            if ($data['type'] === 'dispose') {
                $item->condition = 'disposed';
                $item->assigned_employee_id = null;
            }
            $item->save();

            $item->transactions()->create([...$data, 'quantity' => $qty, 'balance_after' => $item->quantity, 'created_by' => $request->user()->id]);
        });

        return back()->with('success', InventoryTransaction::TYPES[$data['type']].' recorded.');
    }

    public function destroy(InventoryItem $item)
    {
        $item->delete();

        return redirect()->route('inventory.index', ['tab' => $item->type])->with('success', 'Item removed.');
    }

    private function validated(Request $request, ?InventoryItem $item = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['asset', 'consumable'])],
            'name' => ['required', 'string', 'max:255'],
            'inventory_category_id' => ['nullable', 'exists:inventory_categories,id'],
            'new_category' => ['nullable', 'string', 'max:100'],
            'asset_tag' => ['nullable', 'string', 'max:40', Rule::unique('inventory_items')->ignore($item)],
            'unit' => ['nullable', 'string', 'max:20'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['nullable', Rule::in(array_keys(InventoryItem::CONDITIONS))],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data['new_category'])) {
            $data['inventory_category_id'] = InventoryCategory::firstOrCreate(['name' => $data['new_category'], 'type' => $data['type']])->id;
        }

        return [
            ...$data,
            'unit' => $data['unit'] ?? 'pcs',
            'quantity' => $data['quantity'] ?? ($data['type'] === 'asset' ? 1 : 0),
            'min_quantity' => $data['min_quantity'] ?? 0,
            'unit_cost' => $data['unit_cost'] ?? 0,
            'condition' => $data['condition'] ?? 'good',
        ];
    }
}
