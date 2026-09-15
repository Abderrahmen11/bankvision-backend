<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\SarFiling;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\TransactionPolicy;
use App\Observers\ModelAuditObserver;
use App\Services\AuditService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Route::pattern('id', '[0-9]+');

        // Audit every create/update/delete on security-relevant domain models.
        // The users table uses a field whitelist (see ModelAuditObserver) so
        // routine writes like last_login_at stay out of the audit trail.
        User::observe(new ModelAuditObserver(
            hideKeys: ['two_factor_code'],
            trackedFields: ['name', 'email', 'phone', 'role', 'branch_id', 'status'],
        ));
        foreach ([Customer::class, Account::class, Transaction::class, Loan::class, Alert::class, Branch::class, SarFiling::class] as $model) {
            $model::observe(new ModelAuditObserver());
        }

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');
            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
