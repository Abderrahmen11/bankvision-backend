<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

        // Role-based restrictions hook
        if ($user && $user->role === 'compliance') {
            // Compliance officers only see flagged/high-risk accounts
            $query->where(function ($q) {
                $q->where('status', 'frozen')
                  ->orWhereHas('customer', fn ($cq) => $cq->where('risk_level', 'high'))
                  ->orWhereHas('alerts');
            });
        } elseif ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            // Manager & CSR only see accounts belonging to customers in their branch
            $query->whereHas('customer', fn ($q) => $q->where('branch_id', $user->branch_id));
        }

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
    public function getAccountDetails(string|int $id, ?User $user = null): Account
    {
        $user = $user ?? auth()->user();
        $account = Account::with('customer.branch')->findOrFail($id);

        if ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            if ((int) $account->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Account does not belong to your assigned branch.');
            }
        }

        if ($user && $user->role === 'compliance') {
            $isFlaggedOrHighRisk = $account->status === 'frozen'
                || $account->customer?->risk_level === 'high'
                || $account->alerts()->exists();

            if (! $isFlaggedOrHighRisk) {
                throw new AccessDeniedHttpException('Access forbidden. Compliance officers may only view flagged or high-risk accounts.');
            }
        }

        return $account;
    }

    /**
     * Open a new bank account with generated account number.
     */
    public function openAccount(array $data, ?User $user = null): Account
    {
        $user = $user ?? auth()->user();

        if ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            $customer = Customer::findOrFail($data['customer_id']);
            if ((int) $customer->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot open account for customer outside your assigned branch.');
            }
        }

        $data['account_number'] = 'ACC-' . date('Y') . '-' . rand(10000, 99999);
        $data['opened_date']    = $data['opened_date'] ?? date('Y-m-d');
        $data['status']         = 'active';

        $account = Account::create($data);

        return $account->load('customer');
    }

    /**
     * Update account status or interest rate.
     */
    public function updateAccount(Account|string|int $account, array $data, ?User $user = null): Account
    {
        $user = $user ?? auth()->user();
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        if ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            if ((int) $account->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Account does not belong to your assigned branch.');
            }
        }

        $account->update($data);

        return $account->fresh('customer');
    }

    /**
     * Close an account by setting its status to closed.
     */
    public function closeAccount(Account|string|int $account, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        if ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            if ((int) $account->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Account does not belong to your assigned branch.');
            }
        }

        return $account->update(['status' => 'closed']);
    }

    /**
     * Get paginated transactions for an account.
     */
    public function getAccountTransactions(Account|string|int $account, array $filters = [], int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $user = $user ?? auth()->user();
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        if ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            if ((int) $account->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Account does not belong to your assigned branch.');
            }
        }

        return $account->transactions()
            ->with('approver')
            ->filter($filters)
            ->latest('transaction_date')
            ->paginate($perPage);
    }
}
