<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Get aggregated KPI stats for the banking dashboard (cached for 60 seconds).
     */
    public function getStats(): array
    {
        return Cache::remember('dashboard:stats', 60, function () {
            return [
                'total_customers' => Customer::count(),
                'total_accounts' => Account::count(),
                'total_transactions' => Transaction::count(),
                'total_loans' => Loan::count(),
                'open_alerts' => Alert::where('status', 'open')->count(),
                'flagged_transactions' => Transaction::where('status', 'flagged')->count(),
                'pending_loans' => Loan::where('status', 'pending')->count(),
                'active_accounts' => Account::where('status', 'active')->count(),
            ];
        });
    }

    /**
     * Get daily transaction count & volume for the last X days (cached for 300 seconds).
     */
    public function getChartData(int $days = 30): Collection
    {
        return Cache::remember("dashboard:chart_data:{$days}", 300, function () use ($days) {
            return Transaction::query()
                ->selectRaw('DATE(transaction_date) as date, COUNT(*) as count, SUM(amount) as volume')
                ->where('transaction_date', '>=', now()->subDays($days))
                ->groupByRaw('DATE(transaction_date)')
                ->orderBy('date')
                ->get();
        });
    }

    /**
     * Get recent transactions and open alerts for activity feed with field-specific select.
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $recentTransactions = Transaction::query()
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
                'account.customer:id,full_name',
            ])
            ->latest('transaction_date')
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

        $recentAlerts = Alert::query()
            ->select([
                'id',
                'alert_number',
                'alert_type',
                'severity',
                'description',
                'status',
                'assigned_to',
                'created_at',
            ])
            ->with('assignedTo:id,name')
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
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
}
