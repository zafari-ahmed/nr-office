<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentLogController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseHeadController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFollowUpController;
use App\Http\Controllers\ProjectNoteController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteVisitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| M0 Foundation: authentication
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    /*
    | M0 Foundation: search, notifications, files, own account
    | Access rights per role live in Settings › Access rights (App\Support\Access); routes check them with can:.
    */
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
    Route::get('/me', [AccountController::class, 'profile'])->name('account.profile');

    Route::middleware('can:reports.view')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    /*
    | M1 Dashboard
    */
    Route::get('/', DashboardController::class)->name('dashboard');

    /*
    | M2 Clients & Projects (who can open a project is checked in the controllers: all, or managed / team)
    */
    Route::resource('clients', ClientController::class)->except('show')->middleware('can:projects.manage');
    Route::middleware('can:projects.manage')->group(function () {
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status');
    });
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy')->middleware('can:projects.delete');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/{project}/files', [ProjectController::class, 'uploadFiles'])->name('projects.files');
    Route::post('/projects/{project}/follow-ups', [ProjectFollowUpController::class, 'store'])->name('follow-ups.store');
    Route::post('/follow-ups', [ProjectFollowUpController::class, 'storeAny'])->name('follow-ups.store-any');
    Route::patch('/follow-ups/{followUp}/done', [ProjectFollowUpController::class, 'done'])->name('follow-ups.done');
    Route::patch('/follow-ups/{followUp}/snooze', [ProjectFollowUpController::class, 'snooze'])->name('follow-ups.snooze');
    Route::delete('/follow-ups/{followUp}', [ProjectFollowUpController::class, 'destroy'])->name('follow-ups.destroy');
    Route::post('/projects/{project}/notes', [ProjectNoteController::class, 'store'])->name('notes.store');
    Route::patch('/notes/{note}/pin', [ProjectNoteController::class, 'pin'])->name('notes.pin');
    Route::delete('/notes/{note}', [ProjectNoteController::class, 'destroy'])->name('notes.destroy');

    /*
    | M3 Quotations + M5 Document log
    */
    Route::middleware('can:quotations.manage')->group(function () {
        Route::resource('quotations', QuotationController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::patch('/quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.status');
        Route::post('/quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise');
        Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert')->middleware('can:invoices.manage');
        Route::resource('document-logs', DocumentLogController::class)->only(['store', 'update', 'destroy']);
    });
    Route::middleware('can:quotations.view')->group(function () {
        Route::resource('quotations', QuotationController::class)->only(['index', 'show']);
        Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
        Route::resource('document-logs', DocumentLogController::class)->only(['index', 'show']);
    });

    /*
    | M4 Invoices & payments
    */
    Route::middleware('can:invoices.manage')->group(function () {
        Route::resource('invoices', InvoiceController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
        Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('payments.store');
        Route::delete('/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('payments.destroy');
    });
    Route::middleware('can:invoices.view')->group(function () {
        Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    });

    /*
    | M6 Expenses (everyone can add; who sees which is checked in the controller)
    */
    Route::resource('expenses', ExpenseController::class)->except(['create', 'show']);
    Route::middleware('can:expenses.approve')->group(function () {
        Route::patch('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::patch('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    });

    /*
    | M7 Inventory
    */
    Route::middleware('can:inventory.manage')->group(function () {
        Route::resource('inventory', InventoryController::class)->parameters(['inventory' => 'item'])->only(['store', 'update', 'destroy']);
        Route::post('/inventory/{item}/transactions', [InventoryController::class, 'transaction'])->name('inventory.transaction');
    });
    Route::resource('inventory', InventoryController::class)->parameters(['inventory' => 'item'])->only(['index', 'show'])->middleware('can:inventory.view');

    /*
    | M8 Employees (everyone can open their own profile; the directory needs employees.view)
    */
    Route::middleware('can:employees.manage')->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::post('/employees/{employee}/leaves', [EmployeeLeaveController::class, 'store'])->name('leaves.store');
        Route::delete('/leaves/{leave}', [EmployeeLeaveController::class, 'destroy'])->name('leaves.destroy');
        Route::post('/employees/{employee}/documents', [EmployeeController::class, 'uploadDocuments'])->name('employees.documents');
        Route::delete('/employees/{employee}/photo', [EmployeeController::class, 'destroyPhoto'])->name('employees.photo.destroy');
    });
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::post('/employees/{employee}/photo', [EmployeeController::class, 'updatePhoto'])->name('employees.photo.update');
    Route::post('/employees/{employee}/salaries', [EmployeeSalaryController::class, 'store'])->name('salaries.store')->middleware('can:employees.salary');

    /*
    | M9 Site rotation
    */
    Route::get('/rotation', [SiteVisitController::class, 'index'])->name('rotation.index')->middleware('can:rotation.view');
    Route::middleware('can:rotation.manage')->group(function () {
        Route::post('/rotation', [SiteVisitController::class, 'store'])->name('rotation.store');
        Route::put('/rotation/{visit}', [SiteVisitController::class, 'update'])->name('rotation.update');
        Route::patch('/rotation/{visit}/depart', [SiteVisitController::class, 'depart'])->name('rotation.depart');
        Route::patch('/rotation/{visit}/return', [SiteVisitController::class, 'markReturned'])->name('rotation.return');
        Route::patch('/rotation/{visit}/extend', [SiteVisitController::class, 'extend'])->name('rotation.extend');
        Route::patch('/rotation/{visit}/cancel', [SiteVisitController::class, 'cancel'])->name('rotation.cancel');
    });

    /*
    | M10 Settings
    */
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'home'])->name('index');
        Route::resource('users', UserController::class)->except('show')->middleware('can:users.manage');
        Route::middleware('can:access.manage')->group(function () {
            Route::get('/access', [AccessController::class, 'edit'])->name('access.edit');
            Route::put('/access', [AccessController::class, 'update'])->name('access.update');
        });
        Route::middleware('can:settings.manage')->group(function () {
            Route::post('/expense-heads', [ExpenseHeadController::class, 'store'])->name('expense-heads.store');
            Route::put('/expense-heads/{expenseHead}', [ExpenseHeadController::class, 'update'])->name('expense-heads.update');
            Route::get('/{section}', [SettingsController::class, 'show'])->name('show');
            Route::put('/{section}', [SettingsController::class, 'update'])->name('update');
        });
    });
});
