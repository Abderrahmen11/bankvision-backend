<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Get aggregated KPI stats for the banking dashboard.
     */
    public function getStats(): array
    {
        return [
            'total_customers'      => Customer::count(),
            'total_accounts'       => Account::count(),
            'total_transactions'   => Transaction::count(),
            'total_loans'          => Loan::count(),
            'open_alerts'          => Alert::where('status', 'open')->count(),
            'flagged_transactions' => Transaction::where('status', 'flagged')->count(),
            'pending_loans'        => Loan::where('status', 'pending')->count(),
            'active_accounts'      => Account::where('status', 'active')->count(),
        ];
    }

    /**
     * Get daily transaction count & volume for the last 30 days.
     */
    public function getChartData(int $days = 30): Collection
    {
        return Transaction::query()
            ->selectRaw('DATE(transaction_date) as date, COUNT(*) as count, SUM(amount) as volume')
            ->where('transaction_date', '>=', now()->subDays($days))
            ->groupByRaw('DATE(transaction_date)')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get recent transactions and open alerts for activity feed.
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $recentTransactions = Transaction::with('account.customer')
            ->latest('transaction_date')
            ->limit($limit)
            ->get()
            ->map(fn ($t) => [
                'type'        => 'transaction',
                'id'          => $t->id,
                'number'      => $t->transaction_number,
                'description' => ucfirst($t->transaction_type) . ' — ' . $t->currency . ' ' . number_format((float) $t->amount, 2),
                'status'      => $t->status,
                'customer'    => $t->account?->customer?->full_name,
                'date'        => $t->transaction_date?->format('Y-m-d H:i:s'),
            ]);

        $recentAlerts = Alert::with('assignedTo')
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($a) => [
                'type'        => 'alert',
                'id'          => $a->id,
                'number'      => $a->alert_number,
                'description' => $a->description,
                'severity'    => $a->severity,
                'alert_type'  => $a->alert_type,
                'date'        => $a->created_at?->format('Y-m-d H:i:s'),
            ]);

        return [
            'recent_transactions' => $recentTransactions,
            'recent_alerts'       => $recentAlerts,
        ];
    }
}
