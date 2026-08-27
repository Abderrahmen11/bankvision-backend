<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
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
        $isBranchScoped = $user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id;
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
        $isBranchScoped = $user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id;
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
        $isBranchScoped = $user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id;
        $branchId = $isBranchScoped ? $user->branch_id : null;

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
            ->limit($limit)
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
            ->limit($limit)
            ->get()
            ->map(fn($a) => [
                'type' => 'alert',
                'id' => $a->id,
                'number' => $a->alert_number,
                'description' => $a->description,
                'severity' => $a->severity,
                'alert_type' => $a->alert_type,
                'date' => $a->created_at?->format('Y-m-d H:i:s'),
            ]);

        return [
            'recent_transactions' => $recentTransactions,
            'recent_alerts' => $recentAlerts,
        ];
    }

    /**
     * Get comprehensive analytical risk metrics across customers, loans, transactions, and branches.
     */
    public function getRiskAnalysis(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $isManager = $user && $user->role === 'manager' && $user->branch_id;
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
                'customers as total_customers_count',
            ])
            ->get()
            ->map(fn ($b) => [
                'branch_id'                => $b->id,
                'branch_name'              => $b->branch_name,
                'total_customers'          => $b->total_customers_count,
                'high_risk_customers'      => $b->high_risk_customers_count,
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
     * Get aggregate financial, operational, and performance reports for analysts.
     */
    public function getReports(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $isManager = $user && $user->role === 'manager' && $user->branch_id;
        $branchId = $isManager ? $user->branch_id : null;

        $accountQuery = Account::query();
        $loanQuery = Loan::query();
        $txQuery = Transaction::query();
        $customerQuery = Customer::query();

        if ($isManager) {
            $accountQuery->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId));
            $loanQuery->whereHas('customer', fn ($q) => $q->where('branch_id', $branchId));
            $txQuery->whereHas('account.customer', fn ($q) => $q->where('branch_id', $branchId));
            $customerQuery->where('branch_id', $branchId);
        }

        $totalDeposits = (float) (clone $accountQuery)->sum('balance');
        $totalLoanPrincipal = (float) (clone $loanQuery)->sum('principal_amount');
        $totalLoanOutstanding = (float) (clone $loanQuery)->sum('outstanding_balance');

        $transactionsByType = (clone $txQuery)
            ->selectRaw('transaction_type, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        $transactionsByChannel = (clone $txQuery)
            ->selectRaw('channel, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');

        $loansByStatus = (clone $loanQuery)
            ->selectRaw('status, COUNT(*) as count, SUM(outstanding_balance) as total_balance')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $customersByType = (clone $customerQuery)
            ->selectRaw('customer_type, COUNT(*) as count')
            ->groupBy('customer_type')
            ->pluck('count', 'customer_type');

        return [
            'portfolio_summary' => [
                'total_deposits'         => $totalDeposits,
                'total_loan_principal'   => $totalLoanPrincipal,
                'total_loan_outstanding' => $totalLoanOutstanding,
                'loan_to_deposit_ratio'  => round($totalDeposits > 0 ? ($totalLoanOutstanding / $totalDeposits) * 100 : 0, 2),
            ],
            'transaction_breakdown' => [
                'by_type'    => $transactionsByType,
                'by_channel' => $transactionsByChannel,
            ],
            'loan_performance' => $loansByStatus,
            'customer_demographics' => [
                'by_type' => $customersByType,
            ],
        ];
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
