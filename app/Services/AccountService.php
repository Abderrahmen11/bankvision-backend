<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'balance'         => 'balance',
        'opened_date'     => 'opened_date',
        'account_number'  => 'account_number',
        'created_at'      => 'created_at',
        'updated_at'      => 'updated_at', // proxy for last activity
    ];

    /**
     * Get paginated account list with search, filters, sorting, and eager-loaded relations.
     */
    public function getPaginatedAccounts(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage  = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user     = $user ?? auth()->user();

        $query = Account::query()
            ->with('customer.branch')
            ->filter($filters);

        // Role-based restrictions hook (extend here without new endpoints)
        // e.g. if ($user?->role === 'manager') { $query->whereHas('customer', fn ($q) => $q->where('branch_id', $user->branch_id)); }

        $sortColumn    = self::SORT_MAP[$filters['sort_by'] ?? ''] ?? null;
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc');
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        $sortColumn
            ? $query->orderBy($sortColumn, $sortDirection)
            : $query->latest();

        return $query->paginate($perPage);
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
    public function updateAccount(Account|string|int $account, array $data): Account
    {
        $account = $account instanceof Account ? $account : Account::findOrFail($account);
        $account->update($data);

        return $account->fresh('customer');
    }

    /**
     * Close an account by setting its status to closed.
     */
    public function closeAccount(Account|string|int $account): bool
    {
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        return $account->update(['status' => 'closed']);
    }

    /**
     * Get paginated transactions for an account.
     */
    public function getAccountTransactions(Account|string|int $account, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        return $account->transactions()
            ->with('approver')
            ->filter($filters)
            ->latest('transaction_date')
            ->paginate($perPage);
    }
}
