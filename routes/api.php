<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes — all staff (any authenticated role)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user',    [AuthController::class, 'user']);

    /*
    |----------------------------------------------------------------------
    | Dashboard
    |----------------------------------------------------------------------
    */
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats',           [DashboardController::class, 'stats']);
        Route::get('/chart-data',      [DashboardController::class, 'chartData']);
        Route::get('/recent-activity', [DashboardController::class, 'recentActivity']);
    });

    /*
    |----------------------------------------------------------------------
    | Branches — admin only for write operations
    |----------------------------------------------------------------------
    */
    Route::get('branches',         [BranchController::class, 'index']);
    Route::get('branches/{id}',    [BranchController::class, 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::post('branches',        [BranchController::class, 'store']);
        Route::put('branches/{id}',    [BranchController::class, 'update']);
        Route::delete('branches/{id}', [BranchController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Customers — csr, manager, admin, compliance can read; admin/manager write
    |----------------------------------------------------------------------
    */
    Route::get('customers',                     [CustomerController::class, 'index']);
    Route::get('customers/{id}',                [CustomerController::class, 'show']);
    Route::get('customers/{id}/accounts',       [CustomerController::class, 'accounts']);
    Route::get('customers/{id}/loans',          [CustomerController::class, 'loans']);

    Route::middleware('role:admin,manager,csr')->group(function () {
        Route::post('customers',       [CustomerController::class, 'store']);
        Route::put('customers/{id}',   [CustomerController::class, 'update']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('customers/{id}', [CustomerController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Accounts
    |----------------------------------------------------------------------
    */
    Route::get('accounts',                          [AccountController::class, 'index']);
    Route::get('accounts/{id}',                     [AccountController::class, 'show']);
    Route::get('accounts/{id}/transactions',        [AccountController::class, 'transactions']);

    Route::middleware('role:admin,manager,csr')->group(function () {
        Route::post('accounts',      [AccountController::class, 'store']);
        Route::put('accounts/{id}',  [AccountController::class, 'update']);
        Route::delete('accounts/{id}', [AccountController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Transactions
    |----------------------------------------------------------------------
    */
    Route::get('transactions',         [TransactionController::class, 'index']);
    Route::get('transactions/{id}',    [TransactionController::class, 'show']);

    Route::middleware('role:admin,manager,csr')->group(function () {
        Route::post('transactions', [TransactionController::class, 'store']);
    });

    Route::middleware('role:admin,manager,compliance')->group(function () {
        Route::post('transactions/{id}/approve', [TransactionController::class, 'approve']);
        Route::post('transactions/{id}/flag',    [TransactionController::class, 'flag']);
    });

    /*
    |----------------------------------------------------------------------
    | Loans
    |----------------------------------------------------------------------
    */
    Route::get('loans',         [LoanController::class, 'index']);
    Route::get('loans/{id}',    [LoanController::class, 'show']);

    Route::middleware('role:admin,manager,csr')->group(function () {
        Route::post('loans',       [LoanController::class, 'store']);
        Route::put('loans/{id}',   [LoanController::class, 'update']);
    });

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('loans/{id}/approve', [LoanController::class, 'approve']);
    });

    /*
    |----------------------------------------------------------------------
    | Alerts
    |----------------------------------------------------------------------
    */
    Route::get('alerts',         [AlertController::class, 'index']);
    Route::get('alerts/{id}',    [AlertController::class, 'show']);

    Route::middleware('role:admin,compliance,manager')->group(function () {
        Route::post('alerts/{id}/resolve', [AlertController::class, 'resolve']);
        Route::post('alerts/{id}/assign',  [AlertController::class, 'assign']);
    });
});
