<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        // Every access right is a gate, so routes use ->middleware('can:invoices.view') and views use @can.
        foreach (Access::keys() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        Gate::define('access.manage', fn (User $user) => $user->isAdmin());

        // App shell data: notifications, badges and the lists the global quick-add drawers need.
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $view->with([
                'shellNotifications' => $user->notifications()->latest()->limit(8)->get(),
                'shellUnread' => $user->unreadNotifications()->count(),
                'shellOverdueInvoices' => $user->hasPermission('invoices.view') ? Invoice::where('status', 'overdue')->count() : 0,
                'shellOverdueVisits' => $user->hasPermission('rotation.view') ? SiteVisit::onSite()->whereDate('expected_return_date', '<', today())->count() : 0,
                'shellPendingExpenses' => $user->hasPermission('expenses.approve') ? Expense::where('status', 'pending')->count() : 0,
                'quickProjects' => Project::visibleTo($user)->whereNotIn('status', ['completed', 'cancelled'])->orderBy('name')->get(['id', 'name', 'code']),
                'quickHeads' => ExpenseHead::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'links_project']),
                'quickEmployees' => Employee::active()->when(! $user->hasPermission('employees.view'), fn ($q) => $q->whereKey($user->employee_id))
                    ->orderBy('full_name')->get(['id', 'full_name', 'basic_salary'])
                    // Salaries only reach the page for people allowed to see them (used to fill the amount).
                    ->each(fn ($e) => $user->hasPermission('employees.salary') || $e->id === $user->employee_id ?: $e->setAttribute('basic_salary', null)),
                'quickUsers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ]);
        });
    }
}
