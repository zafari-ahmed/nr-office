<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $results = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $user = $request->user();
            $none = fn ($query, string $permission) => $query->when(! $user->hasPermission($permission), fn ($q) => $q->whereRaw('1 = 0'));
            $results = collect()
                ->merge(Project::visibleTo($user)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like))->limit(8)->get()
                    ->map(fn ($p) => ['group' => 'Projects', 'title' => $p->name, 'meta' => $p->code, 'url' => route('projects.show', $p)]))
                ->merge($none(Client::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('company_name', 'like', $like)), 'projects.manage')->limit(5)->get()
                    ->map(fn ($c) => ['group' => 'Clients', 'title' => $c->displayName(), 'meta' => $c->phone, 'url' => route('clients.edit', $c)]))
                ->merge($none(Employee::where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('cnic', 'like', $like)->orWhere('employee_code', 'like', $like)), 'employees.view')->limit(5)->get()
                    ->map(fn ($e) => ['group' => 'Employees', 'title' => $e->full_name, 'meta' => $e->designation, 'url' => route('employees.show', $e)]))
                ->merge($none(Quotation::where(fn ($w) => $w->where('quotation_number', 'like', $like)->orWhere('title', 'like', $like)), 'quotations.view')->limit(5)->get()
                    ->map(fn ($x) => ['group' => 'Quotations', 'title' => $x->label(), 'meta' => $x->title, 'url' => route('quotations.show', $x)]))
                ->merge($none(Invoice::where('invoice_number', 'like', $like), 'invoices.view')->limit(5)->get()
                    ->map(fn ($x) => ['group' => 'Invoices', 'title' => $x->invoice_number, 'meta' => money($x->total), 'url' => route('invoices.show', $x)]));
        }

        if ($request->wantsJson()) {
            return response()->json($results->values());
        }

        return view('search', ['q' => $q, 'results' => $results->groupBy('group')]);
    }
}
