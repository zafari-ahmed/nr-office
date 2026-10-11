<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Project;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month') ? Carbon::parse($request->query('month').'-01') : today()->startOfMonth();
        $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

        $heads = ExpenseHead::where('is_active', true)->orderBy('sort_order')
            ->withSum(['expenses as spent' => fn ($q) => $q->where('status', 'approved')->whereBetween('expense_date', $range)], 'amount')
            ->get();

        $expenses = Expense::with('head', 'project', 'employee', 'attachments', 'creator')
            ->whereBetween('expense_date', $range)
            ->when($request->head, fn ($q, $h) => $q->where('expense_head_id', $h))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project, fn ($q, $p) => $q->where('project_id', $p))
            ->latest('expense_date')->latest('id')->paginate(25)->withQueryString();

        return view('expenses.index', [
            'heads' => $heads,
            'expenses' => $expenses,
            'month' => $month,
            'monthTotal' => (float) Expense::where('status', 'approved')->whereBetween('expense_date', $range)->sum('amount'),
            'pendingCount' => Expense::where('status', 'pending')->count(),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'code']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name', 'basic_salary']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $expense = Expense::create([
            ...collect($data)->except('receipts')->all(),
            'status' => $request->user()->hasRole('admin', 'accounts') ? 'approved' : 'pending',
            'approved_by' => $request->user()->hasRole('admin', 'accounts') ? $request->user()->id : null,
            'approved_at' => $request->user()->hasRole('admin', 'accounts') ? now() : null,
            'created_by' => $request->user()->id,
        ]);
        $expense->attachFiles($request->file('receipts') ?? [], 'receipt');
        Activity::log('created', "Expense added: {$expense->description} · ".money($expense->amount), $expense);

        return back()->with('success', $expense->status === 'pending' ? 'Expense submitted for approval.' : 'Expense added.');
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validated($request);
        $expense->update(collect($data)->except('receipts')->all());
        $expense->attachFiles($request->file('receipts') ?? [], 'receipt');

        return back()->with('success', 'Expense updated.');
    }

    public function approve(Request $request, Expense $expense)
    {
        $expense->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        Activity::log('approved', "Expense approved: {$expense->description} · ".money($expense->amount), $expense);

        return back()->with('success', 'Expense approved.');
    }

    public function reject(Request $request, Expense $expense)
    {
        $expense->update(['status' => 'rejected', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

        return back()->with('success', 'Expense rejected.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }

    private function validated(Request $request): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', (string) $request->payroll_month)) {
            $request->merge(['payroll_month' => $request->payroll_month.'-01']);
        }

        return $request->validate([
            'expense_head_id' => ['required', 'exists:expense_heads,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::METHODS))],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'payroll_month' => ['nullable', 'date'],
            'receipts' => ['nullable', 'array'],
            'receipts.*' => ['file', 'max:10240'],
        ]);
    }
}
