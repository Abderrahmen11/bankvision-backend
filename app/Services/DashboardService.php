<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\Role;
use App\Support\BranchScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Get aggregated KPI stats for the banking dashboard (cached for 60 seconds per branch/role).
     */
    public function getStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);
        $isBranchScoped = $user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id;
        $branchId = $isBranchScoped ? $user->branch_id : null;
        $roleKey = $user ? $user->role : 'global';
        $cacheKey = $isBranchScoped ? "dashboard:stats:branch:{$branchId}:{$roleKey}" : 'dashboard:stats:global';

        return Cache::remember($cacheKey, 60, function () use ($isBranchScoped, $branchId, $user) {
            if ($isBranchScoped) {
                $totalCustomers        = Customer::where('branch_id', $branchId)->count();
                $newCustomersToday     = Customer::where('branch_id', $branchId)->whereDate('created_at', today())->count();
                $totalAccounts         = Account::whereHas('customer', fn ($q) => $q->where('branch_id', $branchId))->count();
                $activeAccounts        = Account::where('status', 'active')->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId))->count();
                $branchBalance         = (float) Account::whereHas('customer', fn ($q) => $q->where('branch_id', $branchId))->sum('balance');
                $totalTransactions     = Transaction::whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId))->count();
                $todayTransactions     = Transaction::whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId))->whereDate('transaction_date', today())->count();
                $customersServedToday  = Transaction::whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId))->whereDate('transaction_date', today())->distinct('account_id')->count('account_id');
                $pendingTransactions   = Transaction::where('status', 'pending')->whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId))->count();
                $flaggedTransactions   = Transaction::where('status', 'flagged')->whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId))->count();
                $totalLoans            = Loan::whereHas('customer', fn ($q) => $q->where('branch_id', $branchId))->count();
                $pendingLoans          = Loan::where('status', 'pending')->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId))->count();

                $openAlerts = Alert::where('status', 'open')
                    ->where(function ($q) use ($branchId, $user) {
                        $q->where('assigned_to', $user->id)
                          ->orWhereHas('assignedTo', fn ($uq) => $uq->where('branch_id', $branchId))
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Customer::class)
                                 ->whereHasMorph('alertable', [Customer::class], fn ($cq) => $cq->where('branch_id', $branchId));
                          })
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Account::class)
                                 ->whereHasMorph('alertable', [Account::class], fn ($aq) => $aq->whereHas('customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                          })
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Transaction::class)
                                 ->whereHasMorph('alertable', [Transaction::class], fn ($tq) => $tq->whereHas('account.customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                          })
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Loan::class)
                                 ->whereHasMorph('alertable', [Loan::class], fn ($lq) => $lq->whereHas('customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                          });
                    })->count();

                $pendingActions  = $pendingLoans + $openAlerts + $flaggedTransactions;
                $branchEmployees = User::where('branch_id', $branchId)->count();

                return [
                    'total_customers'         => $totalCustomers,
                    'new_customers_today'     => $newCustomersToday,
                    'customers_served_today'  => $customersServedToday,
                    'total_accounts'          => $totalAccounts,
                    'active_accounts'         => $activeAccounts,
                    'total_deposits'          => $branchBalance,
                    'branch_balance'          => $branchBalance,
                    'average_account_balance' => round($totalAccounts > 0 ? $branchBalance / $totalAccounts : 0, 2),
                    'total_transactions'      => $totalTransactions,
                    'transaction_volume'      => (float) Transaction::whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId))->sum('amount'),
                    'today_transactions'      => $todayTransactions,
                    'pending_transactions'    => $pendingTransactions,
                    'pending_requests'        => $pendingLoans,
                    'flagged_transactions'    => $flaggedTransactions,
                    'total_loans'             => $totalLoans,
                    'total_loan_amount'       => (float) Loan::whereHas('customer', fn ($q) => $q->where('branch_id', $branchId))->sum('principal_amount'),
                    'pending_loans'           => $pendingLoans,
                    'loan_default_rate'       => round($totalLoans > 0 ? (Loan::where('status', 'defaulted')->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId))->count() / $totalLoans) * 100 : 0, 2),
                    'customer_growth'         => Customer::where('branch_id', $branchId)->where('created_at', '>=', now()->subDays(30))->count(),
                    'open_alerts'             => $openAlerts,
                    'pending_actions'         => $pendingActions,
                    'employee_count'          => $branchEmployees,
                ];
            }

            // Bank-wide aggregated analytics
            $totalCustomers      = Customer::count();
            $newCustomersToday   = Customer::whereDate('created_at', today())->count();
            $totalAccounts       = Account::count();
            $activeAccounts      = Account::where('status', 'active')->count();
            $totalDeposits       = (float) Account::sum('balance');
            $totalTransactions   = Transaction::count();
            $transactionVolume   = (float) Transaction::sum('amount');
            $todayTransactions   = Transaction::whereDate('transaction_date', today())->count();
            $flaggedTransactions = Transaction::where('status', 'flagged')->count();
            $totalLoans          = Loan::count();
            $totalLoanAmount     = (float) Loan::sum('principal_amount');
            $pendingLoans        = Loan::where('status', 'pending')->count();
            $defaultedLoans      = Loan::where('status', 'defaulted')->count();
            $loanDefaultRate     = round($totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0, 2);
            $customerGrowth      = Customer::where('created_at', '>=', now()->subDays(30))->count();
            $openAlerts          = Alert::where('status', 'open')->count();
            $pendingActions      = $pendingLoans + $openAlerts + $flaggedTransactions;
            $employeeCount       = User::count();

            // Distribution metrics for deep analytics
            $loanDistribution = Loan::query()
                ->selectRaw('loan_type, COUNT(*) as count, SUM(principal_amount) as total_principal, SUM(outstanding_balance) as total_outstanding')
                ->groupBy('loan_type')
                ->get()
                ->keyBy('loan_type');

            $customerRiskDistribution = Customer::query()
                ->selectRaw('risk_level, COUNT(*) as count')
                ->groupBy('risk_level')
                ->pluck('count', 'risk_level');

            $branchComparisons = Branch::query()
                ->withCount(['customers', 'users'])
                ->get()
                ->map(fn ($b) => [
                    'branch_id'       => $b->id,
                    'branch_name'     => $b->branch_name,
                    'city'            => $b->city,
                    'customer_count'  => $b->customers_count,
                    'employee_count'  => $b->users_count,
                ]);

            return [
                'total_customers'            => $totalCustomers,
                'total_accounts'             => $totalAccounts,
                'active_accounts'            => $activeAccounts,
                'total_deposits'             => $totalDeposits,
                'branch_balance'             => $totalDeposits,
                'average_account_balance'    => round($totalAccounts > 0 ? $totalDeposits / $totalAccounts : 0, 2),
                'total_transactions'         => $totalTransactions,
                'transaction_volume'         => $transactionVolume,
                'today_transactions'         => $todayTransactions,
                'flagged_transactions'       => $flaggedTransactions,
                'total_loans'                => $totalLoans,
                'total_loan_amount'          => $totalLoanAmount,
                'pending_loans'              => $pendingLoans,
                'loan_default_rate'          => $loanDefaultRate,
                'customer_growth'            => $customerGrowth,
                'open_alerts'                => $openAlerts,
                'pending_actions'            => $pendingActions,
                'employee_count'             => $employeeCount,
                'loan_distribution'          => $loanDistribution,
                'customer_risk_distribution' => $customerRiskDistribution,
                'branch_comparisons'         => $branchComparisons,
            ];
        });
    }

    /**
     * Get daily transaction count & volume for the last X days (cached for 300 seconds).
     */
    public function getChartData(int $days = 30, ?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);
        $isBranchScoped = $user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id;
        $branchId = $isBranchScoped ? $user->branch_id : null;
        $cacheKey = $isBranchScoped ? "dashboard:chart_data:{$days}:branch:{$branchId}" : "dashboard:chart_data:{$days}:global";

        return Cache::remember($cacheKey, 300, function () use ($days, $isBranchScoped, $branchId) {
                $query = Transaction::query()
                    ->selectRaw('DATE(transaction_date) as date, COUNT(*) as count, SUM(amount) as volume')
                    ->where('transaction_date', '>=', now()->subDays($days));

                if ($isBranchScoped) {
                    $query->whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId));
                }

                return $query->groupByRaw('DATE(transaction_date)')
                    ->orderBy('date')
                    ->get();
            });
        }

        /**
         * Get recent transactions and open alerts for activity feed with field-specific select.
         */
        public function getRecentActivity(int $limit = 10, ?User $user = null): array
        {
            $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);
            $isBranchScoped = $user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id;
            $branchId = $isBranchScoped ? $user->branch_id : null;

            // Scoped results are user-specific (assigned alerts join the feed), so
            // branch-scoped roles get a per-user key. The queries fetch a fixed
            // batch of 50 which is sliced to the requested limit afterwards, so
            // every limit shares one cache entry.
            $cacheKey = $isBranchScoped
                ? "dashboard:recent_activity:branch:{$branchId}:user:{$user->id}"
                : 'dashboard:recent_activity:global';

            [$recentTransactions, $recentAlerts] = Cache::remember($cacheKey, 30, function () use ($isBranchScoped, $branchId, $user) {
                $txQuery = Transaction::query()
                    ->select([
                        'id',
                        'transaction_number',
                        'account_id',
                        'transaction_type',
                        'amount',
                        'currency',
                        'status',
                        'transaction_date',
                    ])
                    ->with([
                        'account:id,customer_id',
                        'account.customer:id,full_name,branch_id',
                    ]);

                if ($isBranchScoped) {
                    $txQuery->whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId));
                }

                $recentTransactions = $txQuery->latest('transaction_date')
                    ->limit(50)
                    ->get()
                    ->map(fn($t) => [
                        'type' => 'transaction',
                        'id' => $t->id,
                        'number' => $t->transaction_number,
                        'description' => ucfirst($t->transaction_type) . ' — ' . $t->currency . ' ' . number_format((float) $t->amount, 2),
                        'status' => $t->status,
                        'customer' => $t->account?->customer?->full_name,
                        'date' => $t->transaction_date?->format('Y-m-d H:i:s'),
                    ]);

                $alertQuery = Alert::query()
                    ->select([
                        'id',
                        'alert_number',
                        'alert_type',
                        'severity',
                        'description',
                        'status',
                        'assigned_to',
                        'alertable_type',
                        'alertable_id',
                        'created_at',
                    ])
                    ->with('assignedTo:id,name,branch_id')
                    ->where('status', 'open');

                if ($isBranchScoped) {
                    $alertQuery->where(function ($q) use ($branchId, $user) {
                        $q->where('assigned_to', $user->id)
                          ->orWhereHas('assignedTo', fn ($uq) => $uq->where('branch_id', $branchId))
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Customer::class)
                                 ->whereHasMorph('alertable', [Customer::class], fn ($cq) => $cq->where('branch_id', $branchId));
                          })
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Account::class)
                                 ->whereHasMorph('alertable', [Account::class], fn ($aq) => $aq->whereHas('customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                          })
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Transaction::class)
                                 ->whereHasMorph('alertable', [Transaction::class], fn ($tq) => $tq->whereHas('account.customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                          })
                          ->orWhere(function ($mQ) use ($branchId) {
                              $mQ->where('alertable_type', Loan::class)
                                 ->whereHasMorph('alertable', [Loan::class], fn ($lq) => $lq->whereHas('customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                          });
                    });
                }

                $recentAlerts = $alertQuery->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get()
                    ->map(fn($a) => [
                        'type' => 'alert',
                        'id' => $a->id,
                        'number' => $a->alert_number,
                        'alert_number' => $a->alert_number,
                        'description' => $a->description,
                        'severity' => $a->severity,
                        'alert_type' => $a->alert_type,
                        'date' => $a->created_at?->format('Y-m-d H:i:s'),
                        'created_at' => $a->created_at?->format('Y-m-d H:i:s'),
                    ]);

                return [$recentTransactions->values(), $recentAlerts->values()];
            });

            // Frontend dashboard widgets consume `transactions` / `alerts`;
            // legacy `recent_*` keys are kept for backward compatibility.
            $txSlice = collect($recentTransactions)->take($limit);
            $alertSlice = collect($recentAlerts)->take($limit);

            return [
                'transactions' => $txSlice,
                'alerts' => $alertSlice,
                'recent_transactions' => $txSlice,
                'recent_alerts' => $alertSlice,
            ];
        }

        /**
         * Get comprehensive analytical risk metrics across customers, loans, transactions, and branches.
         */
        public function getRiskAnalysis(?User $user = null): array
        {
            $user = $user ?? auth()->user();
            $isManager = $user && $user->role === Role::Manager->value && $user->branch_id;
            $branchId = $isManager ? $user->branch_id : null;

            $customerQuery = Customer::query();
            $loanQuery = Loan::query();
            $txQuery = Transaction::query();

            if ($isManager) {
                $customerQuery->where('branch_id', $branchId);
                $loanQuery->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId));
                $txQuery->whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId));
            }

            $totalCustomers = (clone $customerQuery)->count();
            $highRiskCustomers = (clone $customerQuery)->where('risk_level', 'high')->count();
            $mediumRiskCustomers = (clone $customerQuery)->where('risk_level', 'medium')->count();
            $lowRiskCustomers = (clone $customerQuery)->where('risk_level', 'low')->count();

            $totalLoans = (clone $loanQuery)->count();
            $delinquentLoans = (clone $loanQuery)->where('status', 'delinquent')->count();
            $defaultedLoans = (clone $loanQuery)->where('status', 'defaulted')->count();
            $totalOutstandingRisk = (float) (clone $loanQuery)->whereIn('status', ['delinquent', 'defaulted'])->sum('outstanding_balance');

            $flaggedTransactions = (clone $txQuery)->where('status', 'flagged')->count();
            $flaggedVolume = (float) (clone $txQuery)->where('status', 'flagged')->sum('amount');
            $wireTransactions = (clone $txQuery)->where('transaction_type', 'wire')->count();
            $wireVolume = (float) (clone $txQuery)->where('transaction_type', 'wire')->sum('amount');

            $branchRisk = Branch::query()
                ->withCount([
                    'customers as high_risk_customers_count' => fn ($q) => $q->where('risk_level', 'high'),
                    'customers as medium_risk_customers_count' => fn ($q) => $q->where('risk_level', 'medium'),
                    'customers as low_risk_customers_count' => fn ($q) => $q->where('risk_level', 'low'),
                    'customers as total_customers_count',
                ])
                ->get()
                ->map(fn ($b) => [
                    'branch_id'                => $b->id,
                    'branch_name'              => $b->branch_name,
                    'total_customers'          => $b->total_customers_count,
                    'high_risk_customers'      => $b->high_risk_customers_count,
                    'medium_risk_customers'    => $b->medium_risk_customers_count,
                    'low_risk_customers'       => $b->low_risk_customers_count,
                    'high_risk_percentage'     => round($b->total_customers_count > 0 ? ($b->high_risk_customers_count / $b->total_customers_count) * 100 : 0, 2),
                ]);

            return [
                'customer_risk' => [
                    'total_customers'      => $totalCustomers,
                    'high_risk_count'      => $highRiskCustomers,
                    'medium_risk_count'    => $mediumRiskCustomers,
                    'low_risk_count'       => $lowRiskCustomers,
                    'high_risk_percentage' => round($totalCustomers > 0 ? ($highRiskCustomers / $totalCustomers) * 100 : 0, 2),
                ],
                'loan_risk' => [
                    'total_loans'             => $totalLoans,
                    'delinquent_loans'        => $delinquentLoans,
                    'defaulted_loans'         => $defaultedLoans,
                    'total_exposed_balance'   => $totalOutstandingRisk,
                    'default_rate_percentage' => round($totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0, 2),
                ],
                'transaction_risk' => [
                    'flagged_count'       => $flaggedTransactions,
                    'flagged_volume'      => $flaggedVolume,
                    'wire_count'          => $wireTransactions,
                    'wire_volume'         => $wireVolume,
                ],
                'branch_risk' => $branchRisk,
            ];
        }

        /**
         * Get comprehensive financial, transaction, loan, and risk reports.
         * Supports optional date range filtering and branch scoping for managers.
         *
         * @param array{start_date?: string, end_date?: string, branch_id?: int|string, period?: string} $filters
         */
        public function getReports(?User $user = null, array $filters = []): array
        {
            $user = $user ?? auth()->user();

            if ($user && $user->role === Role::Csr->value) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Access forbidden. CSR cannot view performance reports.');
            }

            // Branch scoping: managers are scoped to their branch; admins can additionally filter by branch
            $isManager = $user && $user->role === Role::Manager->value && $user->branch_id;
            $branchId  = $isManager ? $user->branch_id : (isset($filters['branch_id']) && $filters['branch_id'] ? (int) $filters['branch_id'] : null);

            // Heaviest endpoint in the app (90+ queries) — cached per scope and
            // filter set for 5 minutes; DashboardCache::flushReports() bumps the
            // generation on any write that affects report data.
            $generation = Cache::get('dashboard:reports:generation', 1);
            $scope      = $isManager ? "branch:{$branchId}" : 'global';
            $cacheKey   = 'dashboard:reports:gen' . $generation . ':' . $scope . ':' . md5(json_encode($filters));

            return Cache::remember($cacheKey, 300, function () use ($user, $filters, $branchId) {

                // Date range from filters or period preset
                $startDate = null;
                $endDate   = now();

                if (!empty($filters['start_date'])) {
                    $startDate = \Carbon\Carbon::parse($filters['start_date'])->startOfDay();
                } elseif (!empty($filters['period'])) {
                    $startDate = match ($filters['period']) {
                        '7d'    => now()->subDays(7)->startOfDay(),
                        '30d'   => now()->subDays(30)->startOfDay(),
                        '90d'   => now()->subDays(90)->startOfDay(),
                        '1y'    => now()->subYear()->startOfDay(),
                        'ytd'   => now()->startOfYear()->startOfDay(),
                        default => null,
                    };
                }

                if (!empty($filters['end_date'])) {
                    $endDate = \Carbon\Carbon::parse($filters['end_date'])->endOfDay();
                }

                // Base query builders with scope applied
                $accountQuery   = Account::query();
                $loanQuery      = Loan::query();
                $txQuery        = Transaction::query();
                $customerQuery  = Customer::query();
                $alertQuery     = Alert::query();

                if ($branchId) {
                    $accountQuery->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId));
                    $loanQuery->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId));
                    $txQuery->whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId));
                    $customerQuery->where('branch_id', $branchId);
                }

                if ($startDate) {
                    $txQuery->where('transaction_date', '>=', $startDate);
                    $loanQuery->where('created_at', '>=', $startDate);
                }
                $txQuery->where('transaction_date', '<=', $endDate);

                // ─────────────────────────────────────────────
                // FINANCIAL DATA
                // ─────────────────────────────────────────────
                $totalDeposits          = (float) (clone $accountQuery)->sum('balance');
                $totalLoanPrincipal     = (float) (clone $loanQuery)->sum('principal_amount');
                $totalLoanOutstanding   = (float) (clone $loanQuery)->sum('outstanding_balance');
                $activeLoanCount        = (clone $loanQuery)->where('status', 'active')->count();
                $avgInterestRate        = (float) (clone $loanQuery)->where('status', 'active')->avg('interest_rate') ?? 0;

                // Approximate interest income: outstanding_balance * avg_interest_rate / 12 (monthly)
                $interestIncome         = round($totalLoanOutstanding * ($avgInterestRate / 100) / 12, 2);
                // Fee income: estimated from wire/transfer volume using the configured fee rate
                $wireTxVolume           = (float) (clone $txQuery)->whereIn('transaction_type', ['wire', 'transfer'])->sum('amount');
                $feeIncome              = round($wireTxVolume * (float) config('banking.estimates.fee_income_rate'), 2);
                $totalRevenue           = $interestIncome + $feeIncome;

                // Deposit interest expense: annualized from the configured savings rate (System Setting)
                $savingsRatePct         = (float) SystemSetting::payload('interest.rates', SystemSetting::INTEREST_DEFAULTS)['savings_rate'];
                $depositInterestExpense = round($totalDeposits * ($savingsRatePct / 100) / 12, 2);
                $operatingCosts         = round($totalRevenue * (float) config('banking.estimates.operating_cost_ratio'), 2);
                $creditProvisions       = (float) (clone $loanQuery)->whereIn('status', ['delinquent', 'defaulted'])->sum('outstanding_balance')
                    * (float) config('banking.estimates.credit_provision_rate');
                $totalExpenses          = $depositInterestExpense + $operatingCosts + $creditProvisions;

                $netIncomeBeforeTax     = $totalRevenue - $totalExpenses;
                $taxProvision           = max(0, round($netIncomeBeforeTax * (float) config('banking.estimates.corporate_tax_rate'), 2));
                $netIncome              = $netIncomeBeforeTax - $taxProvision;
                $profitMargin           = $totalRevenue > 0 ? round(($netIncome / $totalRevenue) * 100, 2) : 0;

                // ─────────────────────────────────────────────
                // TRANSACTION ANALYTICS
                // ─────────────────────────────────────────────
                $transactionsByType = (clone $txQuery)
                    ->selectRaw('transaction_type, COUNT(*) as count, SUM(amount) as total_amount')
                    ->groupBy('transaction_type')
                    ->get()
                    ->map(fn ($r) => [
                        'name'         => ucfirst(str_replace('_', ' ', $r->transaction_type)),
                        'key'          => $r->transaction_type,
                        'count'        => (int) $r->count,
                        'total_amount' => (float) $r->total_amount,
                    ]);

                $transactionsByChannel = (clone $txQuery)
                    ->selectRaw('channel, COUNT(*) as count, SUM(amount) as total_amount')
                    ->groupBy('channel')
                    ->get()
                    ->map(fn ($r) => [
                        'name'         => ucfirst($r->channel ?? 'unknown'),
                        'key'          => $r->channel ?? 'unknown',
                        'count'        => (int) $r->count,
                        'total_amount' => (float) $r->total_amount,
                    ]);

                // Daily trend points (last 30 days or within filter)
                $trendStart = $startDate ?? now()->subDays(30)->startOfDay();
                $dailyTrends = Transaction::query()
                    ->selectRaw('DATE(transaction_date) as date, COUNT(*) as count, SUM(amount) as volume')
                    ->when($branchId, fn ($q) => $q->whereHas('account.customer', fn ($iq) => $iq->where('branch_id', $branchId)))
                    ->where('transaction_date', '>=', $trendStart)
                    ->where('transaction_date', '<=', $endDate)
                    ->groupByRaw('DATE(transaction_date)')
                    ->orderBy('date')
                    ->get()
                    ->map(fn ($r) => [
                        'date'   => $r->date,
                        'count'  => (int) $r->count,
                        'volume' => (float) $r->volume,
                    ]);

                // High-Value Transactions (top 15 by amount)
                $highValueTransactions = (clone $txQuery)
                    ->select(['id', 'transaction_number', 'transaction_type', 'amount', 'channel', 'status', 'transaction_date', 'account_id'])
                    ->with(['account:id,account_number,customer_id', 'account.customer:id,full_name'])
                    ->where('amount', '>=', (int) config('banking.high_value_transaction_threshold'))
                    ->orderByDesc('amount')
                    ->limit(15)
                    ->get()
                    ->map(fn ($t) => [
                        'id'                 => $t->id,
                        'transaction_number' => $t->transaction_number,
                        'type'               => $t->transaction_type,
                        'amount'             => (float) $t->amount,
                        'channel'            => $t->channel,
                        'status'             => $t->status,
                        'date'               => $t->transaction_date?->format('Y-m-d H:i'),
                        'account_number'     => $t->account?->account_number,
                        'customer_name'      => $t->account?->customer?->full_name,
                    ]);

                $totalTxCount  = (clone $txQuery)->count();
                $totalTxVolume = (float) (clone $txQuery)->sum('amount');

                // ─────────────────────────────────────────────
                // LOAN ANALYTICS
                // ─────────────────────────────────────────────
                $loansByStatus = (clone $loanQuery)
                    ->selectRaw('status, COUNT(*) as count, SUM(outstanding_balance) as total_balance, SUM(principal_amount) as total_principal')
                    ->groupBy('status')
                    ->get()
                    ->keyBy('status');

                $loansByType = (clone $loanQuery)
                    ->selectRaw('loan_type, COUNT(*) as count, SUM(principal_amount) as total_principal, SUM(outstanding_balance) as total_outstanding, AVG(interest_rate) as avg_rate')
                    ->groupBy('loan_type')
                    ->get()
                    ->map(fn ($r) => [
                        'loan_type'         => ucfirst(str_replace('_', ' ', $r->loan_type)),
                        'key'               => $r->loan_type,
                        'count'             => (int) $r->count,
                        'total_principal'   => (float) $r->total_principal,
                        'total_outstanding' => (float) $r->total_outstanding,
                        'avg_rate'          => round((float) $r->avg_rate, 2),
                    ]);

                $totalLoans       = (clone $loanQuery)->count();
                $approvedLoans    = (clone $loanQuery)->where('status', 'active')->count();
                $pendingLoans     = (clone $loanQuery)->where('status', 'pending')->count();
                $rejectedLoans    = (clone $loanQuery)->where('status', 'rejected')->count();
                $delinquentLoans  = (clone $loanQuery)->where('status', 'delinquent')->count();
                $defaultedLoans   = (clone $loanQuery)->where('status', 'defaulted')->count();
                $nplCount         = $delinquentLoans + $defaultedLoans;
                $nplRatio         = $totalLoans > 0 ? round(($nplCount / $totalLoans) * 100, 2) : 0;
                $delinquencyRate  = $totalLoans > 0 ? round(($delinquentLoans / $totalLoans) * 100, 2) : 0;

                // ─────────────────────────────────────────────
                // RISK & COMPLIANCE
                // ─────────────────────────────────────────────
                $totalCustomers      = (clone $customerQuery)->count();
                $highRiskCustomers   = (clone $customerQuery)->where('risk_level', 'high')->count();
                $mediumRiskCustomers = (clone $customerQuery)->where('risk_level', 'medium')->count();
                $lowRiskCustomers    = (clone $customerQuery)->where('risk_level', 'low')->count();

                $kycVerified  = (clone $customerQuery)->where('kyc_status', 'verified')->count();
                $kycPending   = (clone $customerQuery)->where('kyc_status', 'pending')->count();
                $kycExpired   = (clone $customerQuery)->where('kyc_status', 'expired')->count();
                $kycRejected  = (clone $customerQuery)->where('kyc_status', 'rejected')->count();

                $openAlerts     = (clone $alertQuery)->where('status', 'open')->count();
                $resolvedAlerts = (clone $alertQuery)->where('status', 'resolved')->count();
                $criticalAlerts = (clone $alertQuery)->where('severity', 'critical')->where('status', 'open')->count();

                // Branch-level risk distribution
                $branchRiskData = Branch::query()
                    ->withCount([
                        'customers as total_customers_count',
                        'customers as high_risk_count'   => fn ($q) => $q->where('risk_level', 'high'),
                        'customers as medium_risk_count' => fn ($q) => $q->where('risk_level', 'medium'),
                    ])
                    ->get()
                    ->map(fn ($b) => [
                        'branch_id'            => $b->id,
                        'branch_name'          => $b->branch_name,
                        'total_customers'      => $b->total_customers_count,
                        'high_risk_count'      => $b->high_risk_count,
                        'medium_risk_count'    => $b->medium_risk_count,
                        'high_risk_percentage' => round($b->total_customers_count > 0 ? ($b->high_risk_count / $b->total_customers_count) * 100 : 0, 2),
                    ]);

                // ─────────────────────────────────────────────
                // BRANCH PERFORMANCE
                // ─────────────────────────────────────────────
                $branchPerformance = Branch::query()
                    ->with('manager:id,name')
                    ->withCount(['customers', 'users'])
                    ->get()
                    ->map(function ($branch) use ($startDate, $endDate) {
                        $deposits = Account::whereHas('customer', fn ($q) => $q->where('branch_id', $branch->id))->sum('balance');
                        $loans    = Loan::whereHas('customer', fn ($q) => $q->where('branch_id', $branch->id))->sum('outstanding_balance');
                        $txVol    = Transaction::whereHas('account.customer', fn ($q) => $q->where('branch_id', $branch->id))
                            ->when($startDate, fn ($q) => $q->where('transaction_date', '>=', $startDate))
                            ->where('transaction_date', '<=', $endDate)
                            ->sum('amount');
                        $txCount  = Transaction::whereHas('account.customer', fn ($q) => $q->where('branch_id', $branch->id))
                            ->when($startDate, fn ($q) => $q->where('transaction_date', '>=', $startDate))
                            ->where('transaction_date', '<=', $endDate)
                            ->count();

                        return [
                            'branch_id'       => $branch->id,
                            'branch_name'     => $branch->branch_name,
                            'city'            => $branch->city,
                            'status'          => $branch->status,
                            'manager'         => $branch->manager?->name,
                            'customer_count'  => $branch->customers_count,
                            'employee_count'  => $branch->users_count,
                            'total_deposits'  => (float) $deposits,
                            'total_loans'     => (float) $loans,
                            'tx_volume'       => (float) $txVol,
                            'tx_count'        => (int) $txCount,
                        ];
                    });

                // ─────────────────────────────────────────────
                // LOAN RISK HISTORY (last 6 months, actual ledger data)
                // ─────────────────────────────────────────────
                $loanRiskHistory = collect(range(5, 0))->map(function ($monthsBack) use ($branchId) {
                    $monthEnd = now()->subMonths($monthsBack)->endOfMonth();

                    $base = Loan::query()
                        ->when($branchId, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('branch_id', $branchId)))
                        ->where('created_at', '<=', $monthEnd);

                    $outstanding = (float) (clone $base)->sum('outstanding_balance');
                    $atRisk      = (float) (clone $base)->whereIn('status', ['delinquent', 'defaulted'])->sum('outstanding_balance');
                    $nplCount    = (clone $base)->whereIn('status', ['delinquent', 'defaulted'])->count();
                    $totalCount  = (clone $base)->count();

                    return [
                        'month'            => $monthEnd->format('M Y'),
                        'delinquency_rate' => $outstanding > 0 ? round(($atRisk / $outstanding) * 100, 2) : 0,
                        'npl_ratio'        => $totalCount > 0 ? round(($nplCount / $totalCount) * 100, 2) : 0,
                    ];
                })->values()->all();

                // ─────────────────────────────────────────────
                // ASSEMBLE RESPONSE
                // ─────────────────────────────────────────────
                return [
                    // Derived financial statements are estimates based on configured ratios
                    'estimated' => true,

                    // ── Financial Statements ──────────────────
                    'income_statement' => [
                        'revenue' => [
                            'interest_income'  => $interestIncome,
                            'fee_income'       => $feeIncome,
                            'total_revenue'    => $totalRevenue,
                        ],
                        'expenses' => [
                            'deposit_interest_expense' => $depositInterestExpense,
                            'operating_costs'          => $operatingCosts,
                            'credit_provisions'        => round($creditProvisions, 2),
                            'total_expenses'           => round($totalExpenses, 2),
                        ],
                        'net_income_before_tax' => round($netIncomeBeforeTax, 2),
                        'tax_provision'         => $taxProvision,
                        'net_income'            => round($netIncome, 2),
                        'profit_margin'         => $profitMargin,
                    ],
                    'balance_sheet' => [
                        'assets' => [
                            'cash_and_reserves'    => round($totalDeposits * (float) config('banking.estimates.cash_reserve_ratio'), 2),
                            'net_loans'            => round($totalLoanOutstanding * (1 - (float) config('banking.estimates.loan_repayment_share')), 2),
                            'other_assets'         => round($totalDeposits * (float) config('banking.estimates.other_assets_ratio'), 2),
                            'total_assets'         => round(
                                $totalDeposits * (float) config('banking.estimates.cash_reserve_ratio')
                                + $totalLoanOutstanding * (1 - (float) config('banking.estimates.loan_repayment_share'))
                                + $totalDeposits * (float) config('banking.estimates.other_assets_ratio'),
                                2
                            ),
                        ],
                        'liabilities' => [
                            'customer_deposits'  => round($totalDeposits * (float) config('banking.estimates.customer_deposits_ratio'), 2),
                            'other_liabilities'  => round($totalDeposits * (float) config('banking.estimates.other_liabilities_ratio'), 2),
                            'total_liabilities'  => round($totalDeposits * ((float) config('banking.estimates.customer_deposits_ratio') + (float) config('banking.estimates.other_liabilities_ratio')), 2),
                        ],
                        'equity' => [
                            'capital'            => round($totalDeposits * (float) config('banking.estimates.equity_ratio'), 2),
                            'retained_earnings'  => round($netIncome, 2),
                            'total_equity'       => round($totalDeposits * (float) config('banking.estimates.equity_ratio') + $netIncome, 2),
                        ],
                    ],
                    'cash_flow' => [
                        'operating'  => round($netIncome + $creditProvisions, 2),
                        'investing'   => round(-$totalLoanPrincipal * (float) config('banking.estimates.loan_repayment_share'), 2),
                        'financing'   => round($totalDeposits * (float) config('banking.estimates.operating_cash_share') - $depositInterestExpense, 2),
                        'net_change'  => round($netIncome + $creditProvisions - $totalLoanPrincipal * (float) config('banking.estimates.loan_repayment_share') + $totalDeposits * (float) config('banking.estimates.operating_cash_share') - $depositInterestExpense, 2),
                    ],

                    // ── Overview KPIs ─────────────────────────
                    'overview' => [
                        'total_revenue'      => round($totalRevenue, 2),
                        'total_expenses'     => round($totalExpenses, 2),
                        'net_profit'         => round($netIncome, 2),
                        'profit_margin'      => $profitMargin,
                        'transaction_volume' => $totalTxVolume,
                        'transaction_count'  => $totalTxCount,
                        'total_deposits'     => $totalDeposits,
                        'total_loan_outstanding' => $totalLoanOutstanding,
                    ],

                    // ── Transaction Analytics ─────────────────
                    'transaction_analytics' => [
                        'by_type'               => $transactionsByType,
                        'by_channel'            => $transactionsByChannel,
                        'daily_trends'          => $dailyTrends,
                        'high_value_transactions' => $highValueTransactions,
                        'total_count'           => $totalTxCount,
                        'total_volume'          => $totalTxVolume,
                    ],

                    // ── Loan Analytics ────────────────────────
                    'loan_risk_history' => $loanRiskHistory,

                    'loan_analytics' => [
                        'portfolio_summary' => [
                            'total_loans'           => $totalLoans,
                            'active_loans'          => $activeLoanCount,
                            'total_principal'       => $totalLoanPrincipal,
                            'total_outstanding'     => $totalLoanOutstanding,
                            'avg_interest_rate'     => round($avgInterestRate, 2),
                            'npl_ratio'             => $nplRatio,
                            'delinquency_rate'      => $delinquencyRate,
                        ],
                        'status_breakdown' => [
                            'approved'   => $approvedLoans,
                            'pending'    => $pendingLoans,
                            'rejected'   => $rejectedLoans,
                            'delinquent' => $delinquentLoans,
                            'defaulted'  => $defaultedLoans,
                        ],
                        'by_type'     => $loansByType,
                    ],

                    // ── Risk & Compliance ─────────────────────
                    'risk_compliance' => [
                        'customer_risk' => [
                            'total'        => $totalCustomers,
                            'high_risk'    => $highRiskCustomers,
                            'medium_risk'  => $mediumRiskCustomers,
                            'low_risk'     => $lowRiskCustomers,
                            'high_pct'     => $totalCustomers > 0 ? round(($highRiskCustomers / $totalCustomers) * 100, 2) : 0,
                            'medium_pct'   => $totalCustomers > 0 ? round(($mediumRiskCustomers / $totalCustomers) * 100, 2) : 0,
                            'low_pct'      => $totalCustomers > 0 ? round(($lowRiskCustomers / $totalCustomers) * 100, 2) : 0,
                        ],
                        'kyc_status' => [
                            'verified' => $kycVerified,
                            'pending'  => $kycPending,
                            'expired'  => $kycExpired,
                            'rejected' => $kycRejected,
                        ],
                        'aml_alerts' => [
                            'open'     => $openAlerts,
                            'resolved' => $resolvedAlerts,
                            'critical' => $criticalAlerts,
                        ],
                        'npl_ratio'       => $nplRatio,
                        'branch_risk'     => $branchRiskData,
                    ],

                    // ── Branch Performance ────────────────────
                    'branch_performance' => $branchPerformance,

                    // ── Backward-compatible loan_portfolio key for Dashboard Widgets ──
                    'loan_portfolio' => [
                        'total_loans'       => $totalLoans,
                        'total_principal'   => $totalLoanPrincipal,
                        'total_outstanding' => $totalLoanOutstanding,
                        'breakdown_by_type' => collect($loansByType)->mapWithKeys(fn ($item) => [
                            $item['key'] => [
                                'count'       => $item['count'],
                                'principal'   => $item['total_principal'],
                                'outstanding' => $item['total_outstanding'],
                            ]
                        ])->toArray(),
                    ],
                ];

            return $reports;
        });
    }

    /**
     * Get Auditor-specific dashboard stats: activity counts, suspicious events, open investigations,
     * failed actions, recent audit activity timeline, and high-risk summaries.
     */
    public function getAuditStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $cacheKey = 'dashboard:audit_stats:global';

        return Cache::remember($cacheKey, 60, function () {
            // Audit log counts
            $totalAuditLogs       = \App\Models\AuditLog::count();
            $todayAuditLogs       = \App\Models\AuditLog::whereDate('created_at', today())->count();
            $thisWeekAuditLogs    = \App\Models\AuditLog::where('created_at', '>=', now()->startOfWeek())->count();

            // Activity breakdown by action
            $auditByAction = \App\Models\AuditLog::query()
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'action');

            // Activity breakdown by table/resource
            $auditByResource = \App\Models\AuditLog::query()
                ->selectRaw('table_name, COUNT(*) as count')
                ->groupBy('table_name')
                ->orderByDesc('count')
                ->pluck('count', 'table_name');

            // Activity breakdown by user role
            $auditByRole = \App\Models\AuditLog::query()
                ->join('users', 'audit_logs.user_id', '=', 'users.id')
                ->selectRaw('users.role, COUNT(*) as count')
                ->groupBy('users.role')
                ->pluck('count', 'users.role');

            // Suspicious / high-risk indicators
            $flaggedTransactions   = Transaction::where('status', 'flagged')->count();
            $openAlerts            = Alert::where('status', 'open')->count();
            $highRiskCustomers     = Customer::where('risk_level', 'high')->count();
            $delinquentLoans       = Loan::where('status', 'delinquent')->count();
            $defaultedLoans        = Loan::where('status', 'defaulted')->count();

            // High-value & suspicious transaction volume
            $flaggedVolume         = (float) Transaction::where('status', 'flagged')->sum('amount');
            $wireVolume            = (float) Transaction::where('transaction_type', 'wire')->sum('amount');

            // Recent audit timeline (last 10 events)
            $recentAuditEvents = \App\Models\AuditLog::with('user:id,name,role')
                ->select(['id', 'user_id', 'action', 'table_name', 'record_id', 'ip_address', 'created_at'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($log) => [
                    'id'          => $log->id,
                    'action'      => $log->action,
                    'resource'    => $log->table_name,
                    'record_id'   => $log->record_id,
                    'ip_address'  => $log->ip_address,
                    'performed_by' => $log->user?->name,
                    'role'        => $log->user?->role,
                    'timestamp'   => $log->created_at?->format('Y-m-d H:i:s'),
                ]);

            return [
                'total_audit_logs'      => $totalAuditLogs,
                'today_audit_logs'      => $todayAuditLogs,
                'this_week_audit_logs'  => $thisWeekAuditLogs,
                'audit_by_action'       => $auditByAction,
                'audit_by_resource'     => $auditByResource,
                'audit_by_role'         => $auditByRole,
                'flagged_transactions'  => $flaggedTransactions,
                'flagged_volume'        => $flaggedVolume,
                'wire_volume'           => $wireVolume,
                'open_alerts'           => $openAlerts,
                'high_risk_customers'   => $highRiskCustomers,
                'delinquent_loans'      => $delinquentLoans,
                'defaulted_loans'       => $defaultedLoans,
                'recent_audit_timeline' => $recentAuditEvents,
            ];
        });
    }

    /**
     * Get investigation-level audit report data: user activity, resource change summaries,
     * high-risk records, and cross-entity event correlations.
     */
    public function getAuditReport(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        // User activity summary (who did what and how many times)
        $userActivitySummary = \App\Models\AuditLog::query()
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->selectRaw('users.id as user_id, users.name, users.role, users.email, COUNT(*) as action_count, MAX(audit_logs.created_at) as last_activity')
            ->groupBy('users.id', 'users.name', 'users.role', 'users.email')
            ->orderByDesc('action_count')
            ->limit(20)
            ->get();

        // Destructive action summary (delete, reject, freeze, flag)
        $destructiveActions = \App\Models\AuditLog::query()
            ->whereIn('action', ['delete', 'reject', 'freeze', 'flag', 'deactivate'])
            ->selectRaw('action, table_name, COUNT(*) as count')
            ->groupBy('action', 'table_name')
            ->orderByDesc('count')
            ->get();

        // High-risk customers for investigation
        $highRiskCustomers = Customer::query()
            ->where('risk_level', 'high')
            ->with(['branch:id,branch_name', 'alerts'])
            ->withCount(['accounts', 'loans', 'alerts'])
            ->select(['id', 'full_name', 'customer_number', 'kyc_status', 'risk_level', 'branch_id', 'created_at'])
            ->latest()
            ->limit(20)
            ->get();

        // Flagged transactions for investigation
        $flaggedTransactions = Transaction::query()
            ->where('status', 'flagged')
            ->with(['account.customer:id,full_name,customer_number', 'approver:id,name,role'])
            ->select(['id', 'transaction_number', 'account_id', 'transaction_type', 'amount', 'currency', 'status', 'approved_by', 'transaction_date'])
            ->latest('transaction_date')
            ->limit(20)
            ->get();

        // Loans in default or delinquency
        $atRiskLoans = Loan::query()
            ->whereIn('status', ['delinquent', 'defaulted'])
            ->with('customer:id,full_name,customer_number')
            ->select(['id', 'loan_number', 'customer_id', 'loan_type', 'status', 'outstanding_balance', 'interest_rate', 'next_payment_date'])
            ->orderByDesc('outstanding_balance')
            ->limit(20)
            ->get();

        return [
            'user_activity_summary' => $userActivitySummary,
            'destructive_actions'   => $destructiveActions,
            'high_risk_customers'   => $highRiskCustomers,
            'flagged_transactions'  => $flaggedTransactions,
            'at_risk_loans'         => $atRiskLoans,
        ];
    }
}
