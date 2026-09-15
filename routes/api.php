<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DashboardLayoutController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Published product interest rates (public marketing data)
Route::get('/public/interest-rates', [SystemSettingController::class, 'publicInterestRates']);

// Two-factor login challenge (password already verified; no token issued yet)
Route::post('/login/2fa', [AuthController::class, 'verifyTwoFactorLogin'])->middleware('throttle:login');
    Route::post('/login/2fa/resend', [AuthController::class, 'resendTwoFactorCode'])->middleware('throttle:login');

/*
|--------------------------------------------------------------------------
| Protected Routes — all staff (any authenticated role)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user',    [AuthController::class, 'user']);

    // In-app notifications (bell icon)
    Route::get('/notifications',            [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all',  [NotificationController::class, 'markAllRead']);

    // Compatibility bridge for external clients; the SPA uses its local index.
    Route::get('/search', [SearchController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Dashboard & Analytical Reports
    |----------------------------------------------------------------------
    */
    Route::prefix('dashboard')->group(function () {
        Route::get('/layout',          [DashboardLayoutController::class, 'index']);
        Route::put('/layout',          [DashboardLayoutController::class, 'update']);
        Route::post('/layout/reset',   [DashboardLayoutController::class, 'reset']);

        Route::get('/stats',           [DashboardController::class, 'stats']);
        Route::get('/chart-data',      [DashboardController::class, 'chartData']);
        Route::get('/recent-activity', [DashboardController::class, 'recentActivity']);
        Route::get('/risk-analysis',   [DashboardController::class, 'riskAnalysis']);

        // Reports restricted from CSR
        Route::middleware('role:admin,manager,compliance,analyst,auditor')->group(function () {
            Route::get('/reports', [DashboardController::class, 'reports']);
        });

        // Auditor investigation dashboard
        Route::middleware('role:admin,auditor')->group(function () {
            Route::get('/audit-stats',  [DashboardController::class, 'auditStats']);
            Route::get('/audit-report', [DashboardController::class, 'auditReport']);
        });
    });

    Route::middleware('role:admin,manager,compliance,analyst,auditor')->group(function () {
        Route::get('reports',               [DashboardController::class, 'reports']);
        Route::get('reports/risk-analysis', [DashboardController::class, 'riskAnalysis']);
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
    | Customers — csr, manager, admin, compliance read; updates restricted per role
    |----------------------------------------------------------------------
    */
    Route::get('customers',                     [CustomerController::class, 'index']);
    Route::get('customers/{id}',                [CustomerController::class, 'show']);
    Route::get('customers/{id}/accounts',       [CustomerController::class, 'accounts']);
    Route::get('customers/{id}/loans',          [CustomerController::class, 'loans']);
    Route::get('customers/{id}/transactions',   [CustomerController::class, 'transactions']);

    Route::middleware('role:admin,manager,csr')->group(function () {
        Route::post('customers',       [CustomerController::class, 'store']);
    });

    Route::middleware('role:admin,manager,csr,compliance')->group(function () {
        Route::put('customers/{id}',   [CustomerController::class, 'update']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('customers/{id}', [CustomerController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Accounts — CSR creates basic accounts; only manager & admin update/close
    |----------------------------------------------------------------------
    */
    Route::get('accounts',                          [AccountController::class, 'index']);
    Route::get('accounts/{id}',                     [AccountController::class, 'show']);
    Route::get('accounts/{id}/transactions',        [AccountController::class, 'transactions']);

    Route::middleware('role:admin,manager,csr')->group(function () {
        Route::post('accounts',        [AccountController::class, 'store']);
    });

    Route::middleware('role:admin,manager')->group(function () {
        Route::put('accounts/{id}',    [AccountController::class, 'update']);
        Route::delete('accounts/{id}', [AccountController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Transactions — CSR/manager/admin record; only manager/admin approve; manager/admin/compliance flag
    |----------------------------------------------------------------------
    */
    Route::get('transactions',         [TransactionController::class, 'index']);
    Route::get('transactions/{id}',    [TransactionController::class, 'show']);

    Route::middleware('role:admin,manager,csr')->group(function () {
        Route::post('transactions', [TransactionController::class, 'store']);
    });

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('transactions/{id}/approve', [TransactionController::class, 'approve']);
    });

    Route::middleware('role:admin,manager,compliance')->group(function () {
        Route::post('transactions/{id}/flag',    [TransactionController::class, 'flag']);
    });

    /*
    |----------------------------------------------------------------------
    | Loans — CSR is read-only; manager & admin create/update/approve
    |----------------------------------------------------------------------
    */
    Route::get('loans',         [LoanController::class, 'index']);
    Route::get('loans/{id}',    [LoanController::class, 'show']);

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('loans',              [LoanController::class, 'store']);
        Route::put('loans/{id}',          [LoanController::class, 'update']);
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

    /*
    |----------------------------------------------------------------------
    | SAR Filings — readable by AML dashboard roles; filed by compliance staff
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin,manager,compliance,analyst,auditor')->group(function () {
        Route::get('sar-filings', [SarFilingController::class, 'index']);
    });

    Route::middleware('role:admin,manager,compliance')->group(function () {
        Route::post('sar-filings', [SarFilingController::class, 'store']);
    });

    /*
    |----------------------------------------------------------------------
    | Users — CSR cannot view staff list; admin manages
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin,manager,compliance,analyst,auditor')->group(function () {
        Route::get('users',         [UserController::class, 'index']);
        Route::get('users/{id}',    [UserController::class, 'show']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('users',        [UserController::class, 'store']);
        Route::put('users/{id}',    [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Audit Logs — admin, auditor, compliance, and branch manager
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin,auditor,compliance,manager')->group(function () {
        Route::get('audit-logs',      [AuditLogController::class, 'index']);
        Route::get('audit-logs/{id}', [AuditLogController::class, 'show']);
    });

    /*
    |----------------------------------------------------------------------
    | Settings — every role manages own profile/security/notifications;
    | system configuration & API token registry are admin only
    |----------------------------------------------------------------------
    */
    Route::prefix('settings')->group(function () {
        // Profile (all roles)
        Route::put('/profile',                [ProfileController::class, 'update']);
        Route::put('/profile/password',       [ProfileController::class, 'updatePassword']);
        Route::post('/profile/avatar',        [ProfileController::class, 'uploadAvatar']);
        Route::delete('/profile/avatar',      [ProfileController::class, 'destroyAvatar']);

        // Notifications & preferences (all roles)
        Route::get('/',                       [UserSettingController::class, 'index']);
        Route::put('/notifications',          [UserSettingController::class, 'updateNotifications']);
        Route::put('/preferences',            [UserSettingController::class, 'updatePreferences']);

        // Security (all roles)
        Route::post('/security/2fa',               [SecurityController::class, 'updateTwoFactor']);
        Route::post('/security/2fa/send-code',     [SecurityController::class, 'sendTwoFactorCode']);
        Route::post('/security/2fa/verify',        [SecurityController::class, 'verifyTwoFactorCode']);
        Route::get('/security/sessions',           [SecurityController::class, 'sessions']);
        Route::delete('/security/sessions/{id}',   [SecurityController::class, 'revokeSession']);
        Route::post('/security/sessions/revoke-all', [SecurityController::class, 'revokeAllSessions']);
        Route::get('/security/login-history',      [SecurityController::class, 'loginHistory']);

        // API tokens (admin only)
        Route::middleware('role:admin')->group(function () {
            Route::get('/security/tokens',         [SecurityController::class, 'tokens']);
            Route::post('/security/tokens',        [SecurityController::class, 'storeToken']);
            Route::delete('/security/tokens/{id}', [SecurityController::class, 'revokeToken']);
        });

        // System configuration (admin only)
        Route::middleware('role:admin')->group(function () {
            Route::get('/system',        [SystemSettingController::class, 'index']);
            Route::put('/system',        [SystemSettingController::class, 'update']);
            Route::get('/system/health', [SystemSettingController::class, 'health']);
        });
    });
});
