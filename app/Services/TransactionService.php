<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'transaction_date' => 'transaction_date',
        'approved_at'      => 'approved_at',
        'amount'           => 'amount',
        'created_at'       => 'created_at',
    ];

    /**
     * Get paginated transactions with search, filters, sorting, and eager-loaded relations.
     */
    public function getPaginatedTransactions(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user    = $user ?? auth()->user();

        $query = Transaction::query()
            ->with(['account.customer', 'approver'])
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && $user->role === 'compliance') {
            // Compliance officers monitor transactions relevant to compliance and suspicious activity:
            // flagged status, high value (>= 10000), wire transfers, or transactions with triggered alerts
            $query->where(function ($q) {
                $q->where('status', 'flagged')
                  ->orWhere('amount', '>=', 10000)
                  ->orWhere('transaction_type', 'wire')
                  ->orWhereHas('alerts');
            });
        } elseif ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            // Manager & CSR only see transactions belonging to accounts in their branch
            $query->whereHas('account.customer', fn ($q) => $q->where('branch_id', $user->branch_id));
        }
        // Admin, Analyst, Auditor: full bank-wide read access — no additional restriction applied

        $sortColumn    = self::SORT_MAP[$filters['sort_by'] ?? ''] ?? null;
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc');
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->latest('transaction_date');
        }

        return $query->paginate($perPage);
    }

    /**
     * Find single transaction with account and approver relations.
     */
    public function getTransactionDetails(string|int $id): Transaction
    {
        return Transaction::with(['account.customer', 'approver'])->findOrFail($id);
    }

    /**
     * Record a new transaction atomically.
     * Status defaults to 'completed' unless explicitly provided.
     * Channel defaults to 'branch' if not supplied to satisfy NOT NULL constraint.
     */
    public function recordTransaction(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $data['transaction_number'] = 'TXN-' . date('Y') . '-' . rand(100000, 999999);
            $data['transaction_date']   = now();
            $data['status']             = $data['status'] ?? 'completed';
            $data['channel']            = $data['channel'] ?? 'branch';

            // Update account balance immediately only for completed transactions
            if ($data['status'] === 'completed') {
                $this->applyBalanceChange($data['account_id'], $data['transaction_type'], (float) $data['amount']);
            }

            $transaction = Transaction::create($data);

            return $transaction->load(['account.customer', 'approver']);
        });
    }

    /**
     * Approve a pending or flagged transaction atomically.
     */
    public function approveTransaction(Transaction|string|int $transaction, User $approver): Transaction
    {
        return DB::transaction(function () use ($transaction, $approver) {
            $transaction = $transaction instanceof Transaction
                ? $transaction
                : Transaction::whereIn('status', ['flagged', 'pending'])->findOrFail($transaction);

            // If approving a pending transaction, now apply the balance change
            if ($transaction->status === 'pending') {
                $this->applyBalanceChange(
                    $transaction->account_id,
                    $transaction->transaction_type,
                    (float) $transaction->amount
                );
            }

            $transaction->update([
                'status'      => 'completed',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $transaction->fresh(['account.customer', 'approver']);
        });
    }

    /**
     * Flag a transaction as suspicious for compliance review.
     * Auto-creates a compliance alert (idempotent — skips if one already exists).
     */
    public function flagTransaction(Transaction|string|int $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $transaction = $transaction instanceof Transaction
                ? $transaction
                : Transaction::where('status', 'completed')->findOrFail($transaction);

            $transaction->update(['status' => 'flagged']);

            // Create compliance alert if not already present
            $alertExists = Alert::where('alertable_type', Transaction::class)
                ->where('alertable_id', $transaction->id)
                ->where('status', 'open')
                ->exists();

            if (! $alertExists) {
                Alert::create([
                    'alertable_type' => Transaction::class,
                    'alertable_id'   => $transaction->id,
                    'alert_type'     => 'suspicious_transaction',
                    'severity'       => 'high',
                    'status'         => 'open',
                    'description'    => "Transaction #{$transaction->transaction_number} flagged for compliance review.",
                ]);
            }

            return $transaction->fresh(['account.customer', 'approver']);
        });
    }

    /**
     * Validate account is active and apply a debit or credit to its balance using pessimistic locking.
     */
    private function applyBalanceChange(int|string $accountId, string $type, float $amount): void
    {
        $account = Account::where('id', $accountId)->lockForUpdate()->firstOrFail();

        if ($account->status === 'frozen') {
            throw ValidationException::withMessages([
                'message' => 'Cannot process transactions on frozen account.',
            ]);
        }

        if ($account->status === 'closed') {
            throw ValidationException::withMessages([
                'message' => 'Cannot process transactions on closed account.',
            ]);
        }

        if ($account->status !== 'active') {
            throw ValidationException::withMessages([
                'message' => 'Transactions cannot be posted to a non-active account.',
            ]);
        }

        if (in_array($type, ['withdrawal', 'transfer', 'wire'], true)) {
            if ((float) $account->balance < $amount) {
                throw ValidationException::withMessages([
                    'message' => 'Insufficient funds. Account balance cannot be negative.',
                ]);
            }
            $account->decrement('balance', $amount);
        } else {
            $account->increment('balance', $amount);
        }
    }
}
