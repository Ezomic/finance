<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategorizeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/households/create', [HouseholdController::class, 'create'])->name('households.create');
    Route::post('/households', [HouseholdController::class, 'store'])->name('households.store');
    Route::post('/households/join', [HouseholdController::class, 'join'])->name('households.join')->middleware('throttle:household-join');
    Route::post('/households/{household}/switch', [HouseholdController::class, 'switch'])->name('households.switch');
    Route::get('/settings/household', [HouseholdController::class, 'settings'])->name('households.settings');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('accounts', AccountController::class);
    Route::post('/accounts/{account}/snapshot', [AccountController::class, 'snapshot'])->name('accounts.snapshot');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::resource('transactions', TransactionController::class)->except(['show']);

    Route::get('/categorize', [CategorizeController::class, 'index'])->name('categorize.index');
    Route::post('/categorize', [CategorizeController::class, 'apply'])->name('categorize.apply');

    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::delete('/budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

    Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
    Route::get('/bills/calendar', [BillController::class, 'calendar'])->name('bills.calendar');
    Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
    Route::put('/bills/{bill}', [BillController::class, 'update'])->name('bills.update');
    Route::post('/bills/{bill}/mark-paid', [BillController::class, 'markPaid'])->name('bills.mark-paid');
    Route::delete('/bills/{bill}', [BillController::class, 'destroy'])->name('bills.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');

    Route::get('/import', [ImportController::class, 'index'])->name('import.index');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');
});
