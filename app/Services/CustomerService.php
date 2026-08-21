<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $data['customer_number'] = 'CUST-' . date('Y') . '-' . rand(10000, 99999);

        $customer = Customer::create($data);

        return $customer->load(['branch', 'relationshipManager']);
    }

    /**
     * Update an existing customer.
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->fresh(['branch', 'relationshipManager']);
    }

    /**
     * Delete a customer.
     */
    public function deleteCustomer(Customer $customer): bool
    {
        return (bool) $customer->delete();
    }

    /**
     * Get paginated accounts for a customer.
     */
    public function getCustomerAccounts(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $customer->accounts()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get paginated loans for a customer.
     */
    public function getCustomerLoans(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $customer->loans()
            ->latest()
            ->paginate($perPage);
    }
}
