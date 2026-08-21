<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountService
{
    /**
     * Get paginated account list with filters and owner customer.
     */
    public function getPaginatedAccounts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Account::query()
            ->with('customer')
            ->filter($filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find account with customer details.
     */
    public function getAccountDetails(string|int $id): Account
    {
        return Account::with('customer')->findOrFail($id);
    }

    /**
     * Open a new bank account with generated account number.
     */
    public function openAccount(array $data): Account
    {
        $data['account_number'] = 'ACC-' . date('Y') . '-' . rand(10000, 99999);
        $data['opened_date']    = $data['opened_date'] ?? date('Y-m-d');
        $data['status']         = 'active';

        $account = Account::create($data);

        return $account->load('customer');
    }

    /**
     * Update account status or interest rate.
     */
    public function updateAccount(Account $account, array $data): Account
    {
        $account->update($data);

        return $account->fresh('customer');
    }

    /**
     * Close an account by setting its status to closed.
     */
    public function closeAccount(Account $account): bool
    {
        return $account->update(['status' => 'closed']);
    }

    /**
     * Get paginated transactions for an account.
     */
    public function getAccountTransactions(Account $account, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $account->transactions()
            ->with('approver')
            ->filter($filters)
            ->latest('transaction_date')
            ->paginate($perPage);
    }
}
