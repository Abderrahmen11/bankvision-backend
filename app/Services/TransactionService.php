<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\Role;
use App\Support\BranchScope;
use App\Support\DashboardCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TransactionService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'transaction_date' => 'transaction_date',
        'approved_at' => 'approved_at',
        'amount' => 'amount',
        'created_at' => 'created_at',
    ];

    /**
     * Get paginated transactions with search, filters, sorting, and eager-loaded relations.
     */
    public function getPaginatedTransactions(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user = $user ?? auth()->user();
        if ($user)
            BranchScope::ensure($user);

        $query = Transaction::query()
            ->with(['account.customer', 'approver'])
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && $user->role === Role::Compliance->value) {
            // Compliance officers monitor transactions relevant to compliance and suspicious activity:
            // flagged status, high value (>= configured threshold), wire transfers, or transactions with triggered alerts
            $query->where(function ($q) {
                $q->where('status', 'flagged')
                    ->orWhere('amount', '>=', (int) config('banking.high_value_transaction_threshold'))
                    ->orWhere('transaction_type', 'wire')
                    ->orWhereHas('alerts');
            });
        } elseif ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            // Manager & CSR only see transactions belonging to accounts in their branch
            $query->whereHas('account.customer', fn($q) => $q->where('branch_id', $user->branch_id));
        }
        // Admin, Analyst, Auditor: full bank-wide read access — no additional restriction applied

        $sortColumn = self::SORT_MAP[$filters['sort_by'] ?? ''] ?? null;
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
    public function getTransactionDetails(string|int $id, ?User $user = null): Transaction
    {
        $user = $user ?? auth()->user();
        if ($user)
            BranchScope::ensure($user);
        $transaction = Transaction::with(['account.customer', 'approver'])->findOrFail($id);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            if ((int) $transaction->account?->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Transaction does not belong to your assigned branch.');
            }
        }

        if ($user && $user->role === Role::Compliance->value) {
            $isComplianceRelevant = $transaction->status === 'flagged'
                || (float) $transaction->amount >= (int) config('banking.high_value_transaction_threshold')
                || $transaction->transaction_type === 'wire'
                || $transaction->alerts()->exists();

            if (!$isComplianceRelevant) {
                throw new AccessDeniedHttpException('Access forbidden. Compliance officers may only view flagged, high-value, or wire transactions.');
            }
        }

        return $transaction;
    }

    /**
     * Record a new transaction atomically.
     * Status defaults to 'completed' unless explicitly provided.
     * Channel defaults to 'branch' if not supplied to satisfy NOT NULL constraint.
     */
    public function recordTransaction(array $data, ?User $user = null): Transaction
    {
        $user = $user ?? auth()->user();
        if ($user)
            BranchScope::ensure($user);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            $account = Account::with('customer')->findOrFail($data['account_id']);
            if ((int) $account->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot record transaction for account outside your assigned branch.');
            }

            // Destination-account branch authorization (IDOR guard)
            if (!empty($data['destination_account_id'])) {
                $destAccount = Account::with('customer')->findOrFail($data['destination_account_id']);
                if ((int) $destAccount->customer?->branch_id !== (int) $user->branch_id) {
                    throw new AccessDeniedHttpException('Access forbidden. Cannot transfer to an account outside your assigned branch.');
                }
            }
        }

        if ($user && $user->role === Role::Csr->value) {
            if (!in_array($data['transaction_type'] ?? '', ['deposit', 'withdrawal'], true)) {
                throw new AccessDeniedHttpException('CSR can only process basic deposits and withdrawals.');
            }
        }

        $transaction = DB::transaction(function () use ($data) {
            $data['transaction_number'] = 'TXN-' . date('Y') . '-' . Str::upper((string) Str::ulid());
            $data['transaction_date'] = now();
            $data['status'] = $data['status'] ?? 'completed';
            $data['channel'] = $data['channel'] ?? 'branch';

            $sourceAccount = Account::whereKey($data['account_id'])->lockForUpdate()->firstOrFail();
            if (($data['currency'] ?? $sourceAccount->currency) !== $sourceAccount->currency) {
                throw ValidationException::withMessages([
                    'currency' => 'Transaction currency must match the source account currency.',
                ]);
            }
            $data['currency'] = $sourceAccount->currency;

            if (in_array($data['transaction_type'], ['transfer', 'wire'], true) && $data['status'] === 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Transfers and wires must be completed atomically.',
                ]);
            }

            $destinationAccount = null;
            if (!empty($data['destination_account_id'])) {
                $destinationAccount = Account::whereKey($data['destination_account_id'])->lockForUpdate()->firstOrFail();
                if ($destinationAccount->currency !== $sourceAccount->currency) {
                    throw ValidationException::withMessages([
                        'destination_account_id' => 'Destination account currency must match the source account currency.',
                    ]);
                }
                if (!$destinationAccount->canTransact()) {
                    throw ValidationException::withMessages([
                        'destination_account_id' => 'Destination account is not active.',
                    ]);
                }
            }

            // Update account balance immediately only for completed transactions
            if ($data['status'] === 'completed') {
                $this->applyBalanceChange($sourceAccount, $data['transaction_type'], $data['amount']);
                if ($destinationAccount) {
                    $this->applyBalanceChange($destinationAccount, 'deposit', $data['amount']);
                }
            }

            $transaction = Transaction::create($data);

            if ($destinationAccount) {
                Transaction::create([
                    ...$data,
                    'transaction_number' => $data['transaction_number'] . '-CREDIT',
                    'account_id' => $destinationAccount->id,
                    'destination_account_id' => null,
                    'transaction_type' => 'deposit',
                    'counterparty' => $sourceAccount->account_number,
                ]);
            }

            return $transaction->load(['account.customer', 'approver']);
        });

        DashboardCache::flushAll();
        return $transaction;
    }

    /**
     * Approve a pending or flagged transaction atomically.
     */
    public function approveTransaction(Transaction|string|int $transaction, User $approver): Transaction
    {
        BranchScope::ensure($approver);
        if ($approver->role === Role::Compliance->value) {
            throw new AccessDeniedHttpException('Access forbidden. Compliance officers cannot approve transactions.');
        }

        $approved = DB::transaction(function () use ($transaction, $approver) {
            $transaction = $transaction instanceof Transaction
                ? $transaction
                : Transaction::with('account.customer')->whereIn('status', ['flagged', 'pending'])->findOrFail($transaction);

            if ($approver->role === Role::Manager->value && $approver->branch_id) {
                if ((int) $transaction->account?->customer?->branch_id !== (int) $approver->branch_id) {
                    throw new AccessDeniedHttpException('Access forbidden. Cannot approve transaction outside your assigned branch.');
                }
            }

            // If approving a pending transaction, now apply the balance change
            if ($transaction->status === 'pending') {
                $account = Account::whereKey($transaction->account_id)->lockForUpdate()->firstOrFail();
                if ($transaction->currency !== $account->currency) {
                    throw ValidationException::withMessages([
                        'currency' => 'Transaction currency must match the account currency.',
                    ]);
                }
                $this->applyBalanceChange(
                    $account,
                    $transaction->transaction_type,
                    (string) $transaction->amount
                );
            }

            $transaction->update([
                'status' => 'completed',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $transaction->fresh(['account.customer', 'approver']);
        });

        DashboardCache::flushAll();
        return $approved;
    }

    /**
     * Flag a transaction as suspicious for compliance review.
     * Auto-creates a compliance alert (idempotent — skips if one already exists).
     */
    public function flagTransaction(Transaction|string|int $transaction, ?User $user = null): Transaction
    {
        $user = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);

        $flagged = DB::transaction(function () use ($transaction, $user) {
            $transaction = $transaction instanceof Transaction
                ? $transaction
                : Transaction::with('account.customer')->where('status', 'completed')->findOrFail($transaction);

            if ($user && $user->role === Role::Manager->value && $user->branch_id) {
                if ((int) $transaction->account?->customer?->branch_id !== (int) $user->branch_id) {
                    throw new AccessDeniedHttpException('Access forbidden. Cannot flag transaction outside your assigned branch.');
                }
            }

            $transaction->update(['status' => 'flagged']);

            // Create compliance alert if not already present
            $alertExists = Alert::where('alertable_type', Transaction::class)
                ->where('alertable_id', $transaction->id)
                ->where('status', 'open')
                ->exists();

            if (!$alertExists) {
                Alert::create([
                    'alertable_type' => Transaction::class,
                    'alertable_id' => $transaction->id,
                    'alert_type' => 'suspicious_transaction',
                    'severity' => 'high',
                    'status' => 'open',
                    'description' => "Transaction #{$transaction->transaction_number} flagged for compliance review.",
                ]);
            }

            DashboardCache::flushAll();

            return $transaction->fresh(['account.customer', 'approver']);
        });

        DashboardCache::flushAll();
        return $flagged;
    }

    /**
     * Validate account is active and apply a debit or credit to its balance using pessimistic locking.
     */
    private function applyBalanceChange(Account $account, string $type, string|int|float $amount): void
    {
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

        $amountMinor = $this->toMinorUnits($amount);
        $balanceMinor = $this->toMinorUnits((string) $account->balance);
        $isDebit = in_array($type, ['withdrawal', 'transfer', 'wire'], true);
        $newBalanceMinor = $isDebit ? $balanceMinor - $amountMinor : $balanceMinor + $amountMinor;

        if ($newBalanceMinor < 0) {
            throw ValidationException::withMessages([
                'message' => 'Insufficient funds. Account balance cannot be negative.',
            ]);
        }

        $account->update(['balance' => number_format($newBalanceMinor / 100, 2, '.', '')]);
    }

    private function toMinorUnits(string|int|float $amount): int
    {
        $value = trim((string) $amount);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must contain no more than two decimal places.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
