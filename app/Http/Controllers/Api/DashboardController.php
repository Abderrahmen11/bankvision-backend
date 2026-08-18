<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Return high-level KPI stats for the dashboard.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_customers'    => Customer::count(),
                'total_accounts'     => Account::count(),
                'total_transactions' => Transaction::count(),
                'total_loans'        => Loan::count(),
                'open_alerts'        => Alert::where('status', 'open')->count(),
                'flagged_transactions' => Transaction::where('status', 'flagged')->count(),
                'pending_loans'      => Loan::where('status', 'pending')->count(),
                'active_accounts'    => Account::where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * Return daily transaction volume for the last 30 days (for chart data).
     */
    public function chartData(): JsonResponse
    {
        $data = Transaction::query()
            ->selectRaw('DATE(transaction_date) as date, COUNT(*) as count, SUM(amount) as volume')
            ->where('transaction_date', '>=', now()->subDays(30))
            ->groupByRaw('DATE(transaction_date)')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Return recent transactions and open alerts for the activity feed.
     */
    public function recentActivity(): JsonResponse
    {
        $recentTransactions = Transaction::with('account.customer')
            ->latest('transaction_date')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'type'        => 'transaction',
                'id'          => $t->id,
                'number'      => $t->transaction_number,
                'description' => ucfirst($t->transaction_type) . ' — ' . $t->currency . ' ' . number_format($t->amount, 2),
                'status'      => $t->status,
                'customer'    => $t->account?->customer?->full_name,
                'date'        => $t->transaction_date?->format('Y-m-d H:i:s'),
            ]);

        $recentAlerts = Alert::with('assignedTo')
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->limit(10)
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

        return response()->json([
            'success' => true,
            'data'    => [
                'recent_transactions' => $recentTransactions,
                'recent_alerts'       => $recentAlerts,
            ],
        ]);
    }
}
