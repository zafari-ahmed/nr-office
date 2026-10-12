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
        $user = $request->user();
        $month = $request->query('month') ? Carbon::parse($request->query('month').'-01') : today()->startOfMonth();
        $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

        $heads = ExpenseHead::where('is_active', true)->orderBy('sort_order')
            ->withSum(['expenses as spent' => fn ($q) => $q->visibleTo($user)->where('status', 'approved')->whereBetween('expense_date', $range)], 'amount')
            ->get();

        $expenses = Expense::visibleTo($user)->with('head', 'project', 'employee', 'attachments', 'creator')
            ->whereBetween('expense_date', $range)
            ->when($request->head, fn ($q, $h) => $q->where('expense_head_id', $h))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project, fn ($q, $p) => $q->where('project_id', $p))
            ->latest('expense_date')->latest('id')->paginate(25)->withQueryString();

        return view('expenses.index', [
            'heads' => $heads,
            'expenses' => $expenses,
            'month' => $month,
            'monthTotal' => (float) Expense::visibleTo($user)->where('status', 'approved')->whereBetween('expense_date', $range)->sum('amount'),
            'pendingCount' => Expense::visibleTo($user)->where('status', 'pending')->count(),
            'pendingAmount' => (float) Expense::visibleTo($user)->where('status', 'pending')->sum('amount'),
            'budgetTotal' => (float) \App\Models\ExpenseHead::where('is_active', true)->sum('monthly_budget'),
            'seeAll' => $user->hasPermission('expenses.view_all'),
            'projects' => Project::visibleTo($user)->orderBy('name')->get(['id', 'name', 'code']),
            'employees' => Employee::active()->when(! $user->hasPermission('employees.view'), fn ($q) => $q->whereKey($user->employee_id))
                ->orderBy('full_name')->get(['id', 'full_name', 'basic_salary']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $approver = $request->user()->hasPermission('expenses.approve');
        $expense = Expense::create([
            ...collect($data)->except('receipts')->all(),
            'status' => $approver ? 'approved' : 'pending',
            'approved_by' => $approver ? $request->user()->id : null,
            'approved_at' => $approver ? now() : null,
            'created_by' => $request->user()->id,
        ]);
        $expense->attachFiles($request->file('receipts') ?? [], 'receipt');
        Activity::log('created', "Expense added: {$expense->description} · ".money($expense->amount), $expense);

        return back()->with('success', $expense->status === 'pending' ? 'Expense submitted for approval.' : 'Expense added.');
    }

    public function update(Request $request, Expense $expense)
    {
        $this->ensureCanChange($request, $expense);
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

    public function destroy(Request $request, Expense $expense)
    {
        $this->ensureCanChange($request, $expense);
        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }

    /** Approvers can change any expense; others only their own while it is still pending. */
    private function ensureCanChange(Request $request, Expense $expense): void
    {
        $user = $request->user();
        abort_unless($user->hasPermission('expenses.approve') || ($user->ownsExpense($expense) && $expense->status === 'pending'), 403, 'You can only change your own pending expenses.');
    }

    private function validated(Request $request): array
    {
        $user = $request->user();
        // Without the directory, people can only file expenses for themselves and for projects they can see.
        if ($request->filled('employee_id') && ! $user->hasPermission('employees.view')) {
            abort_unless((int) $request->employee_id === (int) $user->employee_id, 403);
        }
        if ($request->filled('project_id') && ($project = Project::find($request->project_id))) {
            $this->ensureCanSeeProject($project);
        }

        if (preg_match('/^\d{4}-\d{2}$/', (string) $request->payroll_month)) {
            $request->merge(['payroll_month' => $request->payroll_month.'-01']);
        }

        // Salary is paid to an employee for a month: no vendor or project, and the description writes itself.
        $head = ExpenseHead::find($request->expense_head_id);
        $isSalary = $head?->slug === 'salary';
        // Only heads marked "links to project" (e.g. Project expenses) carry a project, and there it is required.
        $linksProject = (bool) $head?->links_project;

        $data = $request->validate([
            'expense_head_id' => ['required', 'exists:expense_heads,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => [$isSalary ? 'nullable' : 'required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::METHODS))],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'project_id' => [$linksProject ? 'required' : 'nullable', 'exists:projects,id'],
            'employee_id' => [$isSalary ? 'required' : 'nullable', 'exists:employees,id'],
            'payroll_month' => [$isSalary ? 'required' : 'nullable', 'date'],
            'receipts' => ['nullable', 'array'],
            'receipts.*' => ['file', 'max:10240'],
        ], ['employee_id.required' => 'Choose whose salary this is.', 'payroll_month.required' => 'Choose the salary month.', 'project_id.required' => 'Choose the project this expense is for.']);

        if (! $linksProject) {
            $data['project_id'] = null;
        }

        if ($isSalary) {
            $month = Carbon::parse($data['payroll_month']);
            $data['description'] = 'Salary '.$month->format('M Y').' – '.Employee::find($data['employee_id'])->full_name;
            $data['vendor'] = null;
            $data['project_id'] = null;
        } else {
            $data['employee_id'] = null;
            $data['payroll_month'] = null;
        }

        return $data;
    }
}
