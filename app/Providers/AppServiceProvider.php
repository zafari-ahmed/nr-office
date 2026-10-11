<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Project;
use App\Models\User;
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

        // App shell data: notifications, badges and the lists the global quick-add drawers need.
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $view->with([
                'shellNotifications' => $user->notifications()->latest()->limit(8)->get(),
                'shellUnread' => $user->unreadNotifications()->count(),
                'shellPendingExpenses' => $user->hasRole('admin', 'manager', 'accounts') ? Expense::where('status', 'pending')->count() : 0,
                'quickProjects' => Project::whereNotIn('status', ['completed', 'cancelled'])->orderBy('name')->get(['id', 'name', 'code']),
                'quickHeads' => ExpenseHead::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
                'quickEmployees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name']),
                'quickUsers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ]);
        });
    }
}
