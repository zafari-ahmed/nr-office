<?php

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
    | M0 Foundation: search, notifications, files
    */
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    /*
    | M1 Dashboard
    */
    Route::get('/', DashboardController::class)->name('dashboard');

    /*
    | M2 Clients & Projects
    */
    Route::resource('clients', ClientController::class)->except('show');
    Route::resource('projects', ProjectController::class);
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status');
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
    | M3 Quotations
    */
    Route::resource('quotations', QuotationController::class);
    Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    Route::patch('/quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.status');
    Route::post('/quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');

    /*
    | M4 Invoices & payments
    */
    Route::resource('invoices', InvoiceController::class);
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
    Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('payments.store');
    Route::delete('/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('payments.destroy');

    /*
    | M5 Document log
    */
    Route::resource('document-logs', DocumentLogController::class)->except(['create', 'edit']);

    /*
    | M6 Expenses
    */
    Route::resource('expenses', ExpenseController::class)->except(['create', 'show']);
    Route::patch('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve')->middleware('role:admin,manager,accounts');
    Route::patch('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject')->middleware('role:admin,manager,accounts');

    /*
    | M7 Inventory
    */
    Route::resource('inventory', InventoryController::class)->parameters(['inventory' => 'item'])->except(['create', 'edit']);
    Route::post('/inventory/{item}/transactions', [InventoryController::class, 'transaction'])->name('inventory.transaction');

    /*
    | M8 Employees
    */
    Route::resource('employees', EmployeeController::class);
    Route::post('/employees/{employee}/salaries', [EmployeeSalaryController::class, 'store'])->name('salaries.store')->middleware('role:admin,accounts');
    Route::post('/employees/{employee}/leaves', [EmployeeLeaveController::class, 'store'])->name('leaves.store');
    Route::delete('/leaves/{leave}', [EmployeeLeaveController::class, 'destroy'])->name('leaves.destroy');
    Route::post('/employees/{employee}/documents', [EmployeeController::class, 'uploadDocuments'])->name('employees.documents');

    /*
    | M9 Site rotation
    */
    Route::get('/rotation', [SiteVisitController::class, 'index'])->name('rotation.index');
    Route::post('/rotation', [SiteVisitController::class, 'store'])->name('rotation.store');
    Route::put('/rotation/{visit}', [SiteVisitController::class, 'update'])->name('rotation.update');
    Route::patch('/rotation/{visit}/depart', [SiteVisitController::class, 'depart'])->name('rotation.depart');
    Route::patch('/rotation/{visit}/return', [SiteVisitController::class, 'markReturned'])->name('rotation.return');
    Route::patch('/rotation/{visit}/extend', [SiteVisitController::class, 'extend'])->name('rotation.extend');
    Route::patch('/rotation/{visit}/cancel', [SiteVisitController::class, 'cancel'])->name('rotation.cancel');

    /*
    | M10 Settings (admin & manager)
    */
    Route::middleware('role:admin,manager')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', fn () => redirect()->route('settings.show', 'company'))->name('index');
        Route::resource('users', UserController::class)->except('show');
        Route::post('/expense-heads', [ExpenseHeadController::class, 'store'])->name('expense-heads.store');
        Route::put('/expense-heads/{expenseHead}', [ExpenseHeadController::class, 'update'])->name('expense-heads.update');
        Route::get('/{section}', [SettingsController::class, 'show'])->name('show');
        Route::put('/{section}', [SettingsController::class, 'update'])->name('update');
    });
});
