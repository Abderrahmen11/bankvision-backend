<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionService
{
    /**
     * Get paginated transactions with filters and eager loaded relations.
     */
    public function getPaginatedTransactions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account.customer', 'approver'])
            ->filter($filters)
            ->latest('transaction_date')
            ->paginate($perPage);
    }

    /**
     * Find single transaction with account and approver relations.
     */
    public function getTransactionDetails(string|int $id): Transaction
    {
        return Transaction::with(['account.customer', 'approver'])->findOrFail($id);
    }

    /**
     * Record a new transaction with generated transaction number.
     */
    public function recordTransaction(array $data): Transaction
    {
        $data['transaction_number'] = 'TXN-' . date('Y') . '-' . rand(100000, 999999);
        $data['transaction_date']   = now();
        $data['status']             = 'completed';

        $transaction = Transaction::create($data);

        return $transaction->load(['account.customer', 'approver']);
    }

    /**
     * Approve a flagged transaction.
     */
    public function approveTransaction(Transaction $transaction, User $approver): Transaction
    {
        $transaction->update([
            'status'      => 'completed',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $transaction->fresh(['account.customer', 'approver']);
    }

    /**
     * Flag a transaction as suspicious for compliance review.
     */
    public function flagTransaction(Transaction $transaction): Transaction
    {
        $transaction->update(['status' => 'flagged']);

        return $transaction->fresh(['account.customer', 'approver']);
    }
}
