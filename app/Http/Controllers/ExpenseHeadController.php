<?php

namespace App\Http\Controllers;

use App\Models\ExpenseHead;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseHeadController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);
        ExpenseHead::create([...$data, 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)), 'sort_order' => ExpenseHead::max('sort_order') + 1]);

        return back()->with('success', 'Expense head added.');
    }

    public function update(Request $request, ExpenseHead $expenseHead)
    {
        $expenseHead->update([...$this->validated($request), 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Expense head updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'max:20'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['monthly_budget'] ??= 0;

        return $data;
    }
}
