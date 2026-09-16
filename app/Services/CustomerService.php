<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\DashboardCache;use App\Models\Transaction;
use App\Models\User;
use App\Enums\Role;
use App\Support\BranchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CustomerService
{
    /**
     * Get paginated customer list with filters, search, sorting, and eager-loaded relations.
     */
    public function getPaginatedCustomers(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);

        $query = Customer::query()
            ->with(['branch', 'relationshipManager'])
            ->withCount(['accounts', 'loans'])
            ->withSum('accounts as total_balance', 'balance')
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            // Manager & CSR only see customers belonging to their assigned branch
            $query->where('branch_id', $user->branch_id);
        }
        // Admin, Compliance, Analyst, Auditor: full bank-wide read access

        // Sorting by registration_date or specified column with direction
        $sortBy = $filters['sort_by'] ?? ($filters['sort_direction'] ?? $filters['direction'] ?? $filters['order'] ?? null ? 'registration_date' : null);
        $direction = strtolower($filters['sort_direction'] ?? $filters['direction'] ?? $filters['order'] ?? 'desc');
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        if ($sortBy && in_array($sortBy, ['registration_date', 'created_at', 'full_name', 'customer_number'], true)) {
            $query->orderBy($sortBy, $direction);
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    /**
     * Find customer with accounts and loans counts and relations.
     */
    public function getCustomerDetails(string|int $id, ?User $user = null): Customer
    {
        $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);
        $customer = Customer::with(['branch', 'relationshipManager'])
            ->withCount(['accounts', 'loans'])
            ->withSum('accounts as total_balance', 'balance')
            ->findOrFail($id);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            if ((int) $customer->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Customer does not belong to your assigned branch.');
            }
        }

        return $customer;
    }

    /**
     * Create a new customer and generate a unique customer number.
     */
    public function createCustomer(array $data, ?User $user = null): Customer
    {
        $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            $data['branch_id'] = $user->branch_id;
        }

        if (array_key_exists('relationship_manager_id', $data) && $data['relationship_manager_id'] !== null) {
            $effectiveBranchId = (int) ($data['branch_id'] ?? 0);
            $this->validateRelationshipManager($data['relationship_manager_id'], $effectiveBranchId);
        }

        $data['customer_number']   = 'CUST-' . date('Y') . '-' . Str::upper((string) Str::ulid());
        $data['registration_date'] = $data['registration_date'] ?? now()->toDateString();
        $data['kyc_status']        = $data['kyc_status'] ?? 'pending';

        $customer = Customer::create($data);
        $customer = $customer->load(['branch', 'relationshipManager']);
        DashboardCache::flushReports();
        return $customer;
    }

    /**
     * Update an existing customer.
     */
    public function updateCustomer(Customer|string|int $customer, array $data, ?User $user = null): Customer
    {
        $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);
        $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            if ((int) $customer->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Customer does not belong to your assigned branch.');
            }
        }

        if ($user && $user->role === Role::Csr->value) {
            // CSR cannot verify KYC or update risk level
            if (array_key_exists('kyc_status', $data) || array_key_exists('risk_level', $data)) {
                throw new AccessDeniedHttpException('CSR cannot update KYC status or risk level.');
            }
            if (isset($data['branch_id']) && (int) $data['branch_id'] !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot change customer branch.');
            }
        }

        if ($user && $user->role === Role::Compliance->value) {
            // Compliance cannot edit customer personal info
            $personalFields = ['full_name', 'email', 'phone', 'address', 'city', 'relationship_manager_id'];
            foreach ($personalFields as $field) {
                if (array_key_exists($field, $data)) {
                    throw new AccessDeniedHttpException('Compliance officers cannot edit customer personal information.');
                }
            }
        }

        if (array_key_exists('relationship_manager_id', $data) && $data['relationship_manager_id'] !== null) {
            $effectiveBranchId = (int) ($data['branch_id'] ?? $customer->branch_id);
            $this->validateRelationshipManager($data['relationship_manager_id'], $effectiveBranchId);
        } elseif (isset($data['branch_id']) && $customer->relationship_manager_id) {
            $this->validateRelationshipManager($customer->relationship_manager_id, (int) $data['branch_id']);
        }

        $customer->update($data);
        $customer = $customer->fresh(['branch', 'relationshipManager']);
        DashboardCache::flushReports();
        return $customer;
    }

    /**
     * Validate that the relationship manager is eligible for the customer's branch.
     *
     * @throws ValidationException
     */
    private function validateRelationshipManager(mixed $managerId, int $customerBranchId): void
    {
        $manager = User::find($managerId);

        if (!$manager) {
            throw ValidationException::withMessages([
                'relationship_manager_id' => ['The selected relationship manager does not exist.'],
            ]);
        }

        if ((int) $manager->branch_id !== (int) $customerBranchId) {
            throw ValidationException::withMessages([
                'relationship_manager_id' => ['The relationship manager must belong to the same branch as the customer.'],
            ]);
        }

        if (!in_array($manager->role, ['csr', 'manager'], true)) {
            throw ValidationException::withMessages([
                'relationship_manager_id' => ['The relationship manager must have an eligible role (manager or csr).'],
            ]);
        }

        if ($manager->status !== 'active') {
            throw ValidationException::withMessages([
                'relationship_manager_id' => ['The relationship manager must be an active staff member.'],
            ]);
        }
    }

    /**
     * Delete a customer — blocked if they have funded accounts or outstanding loans.
     */
    public function deleteCustomer(Customer|string|int $customer): bool
    {
        return DB::transaction(function () use ($customer) {
            $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

            $hasFundedAccounts = $customer->accounts()
                ->where('status', 'active')
                ->where('balance', '>', 0)
                ->exists();

            $hasActiveLoans = $customer->loans()
                ->whereIn('status', ['pending', 'active', 'delinquent'])
                ->exists();

            if ($hasFundedAccounts || $hasActiveLoans) {
                throw ValidationException::withMessages([
                    'message' => 'Cannot delete a customer with active accounts holding funds or outstanding loans.',
                ]);
            }

            return (bool) $customer->delete();
        });
    }

    /**
     * Get paginated accounts for a customer.
     */
    public function getCustomerAccounts(Customer|string|int $customer, int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);
        $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            if ((int) $customer->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Customer does not belong to your assigned branch.');
            }
        }

        return $customer->accounts()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get paginated loans for a customer.
     */
    public function getCustomerLoans(Customer|string|int $customer, int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);
        $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            if ((int) $customer->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Customer does not belong to your assigned branch.');
            }
        }

        return $customer->loans()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get paginated transactions for all accounts belonging to a customer.
     */
    public function getCustomerTransactions(Customer|string|int $customer, int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $user = $user ?? auth()->user();
            if ($user) BranchScope::ensure($user);
        $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            if ((int) $customer->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Customer does not belong to your assigned branch.');
            }
        }

        return Transaction::query()
            ->whereIn('account_id', $customer->accounts()->select('id'))
            ->with(['account', 'approver'])
            ->latest('transaction_date')
            ->paginate($perPage);
    }
}
