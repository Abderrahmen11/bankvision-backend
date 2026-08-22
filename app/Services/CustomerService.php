<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * Get paginated customer list with filters and eager-loaded relations.
     */
    public function getPaginatedCustomers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Customer::query()
            ->with(['branch', 'relationshipManager'])
            ->withCount(['accounts', 'loans'])
            ->filter($filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find customer with accounts and loans counts and relations.
     */
    public function getCustomerDetails(string|int $id): Customer
    {
        return Customer::with(['branch', 'relationshipManager'])
            ->withCount(['accounts', 'loans'])
            ->findOrFail($id);
    }

    /**
     * Create a new customer and generate a unique customer number.
     */
    public function createCustomer(array $data): Customer
    {
        $data['customer_number']   = 'CUST-' . date('Y') . '-' . rand(10000, 99999);
        $data['registration_date'] = $data['registration_date'] ?? now()->toDateString();
        $data['kyc_status']        = $data['kyc_status'] ?? 'pending';

        $customer = Customer::create($data);

        return $customer->load(['branch', 'relationshipManager']);
    }

    /**
     * Update an existing customer.
     */
    public function updateCustomer(Customer|string|int $customer, array $data): Customer
    {
        $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);
        $customer->update($data);

        return $customer->fresh(['branch', 'relationshipManager']);
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
    public function getCustomerAccounts(Customer|string|int $customer, int $perPage = 15): LengthAwarePaginator
    {
        $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        return $customer->accounts()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get paginated loans for a customer.
     */
    public function getCustomerLoans(Customer|string|int $customer, int $perPage = 15): LengthAwarePaginator
    {
        $customer = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        return $customer->loans()
            ->latest()
            ->paginate($perPage);
    }
}
