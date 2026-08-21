<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LoanResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * List customers with search and filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = $this->customerService->getPaginatedCustomers(
            $request->only(['search', 'type', 'kyc_status', 'risk_level'])
        );

        return CustomerResource::collection($customers);
    }

    /**
     * Show a single customer with details.
     */
    public function show(string $id): CustomerResource
    {
        $customer = $this->customerService->getCustomerDetails($id);

        return CustomerResource::make($customer);
    }

    /**
     * Create a new customer record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name'               => ['required', 'string', 'max:255'],
            'email'                   => ['required', 'email', 'unique:customers,email'],
            'phone'                   => ['required', 'string', 'max:20'],
            'address'                 => ['nullable', 'string'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'customer_type'           => ['required', 'in:premium,regular,business'],
            'kyc_status'              => ['sometimes', 'in:verified,pending,expired'],
            'risk_level'              => ['sometimes', 'in:low,medium,high'],
            'registration_date'       => ['sometimes', 'date'],
            'branch_id'               => ['required', 'exists:branches,id'],
            'relationship_manager_id' => ['nullable', 'exists:users,id'],
        ]);

        $customer = $this->customerService->createCustomer($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data'    => CustomerResource::make($customer),
        ], 201);
    }

    /**
     * Update an existing customer.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'full_name'               => ['sometimes', 'string', 'max:255'],
            'email'                   => ['sometimes', 'email', "unique:customers,email,{$customer->id}"],
            'phone'                   => ['sometimes', 'string', 'max:20'],
            'address'                 => ['nullable', 'string'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'customer_type'           => ['sometimes', 'in:premium,regular,business'],
            'kyc_status'              => ['sometimes', 'in:verified,pending,expired'],
            'risk_level'              => ['sometimes', 'in:low,medium,high'],
            'branch_id'               => ['sometimes', 'exists:branches,id'],
            'relationship_manager_id' => ['nullable', 'exists:users,id'],
        ]);

        $updated = $this->customerService->updateCustomer($customer, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data'    => CustomerResource::make($updated),
        ]);
    }

    /**
     * Delete a customer record.
     */
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $this->customerService->deleteCustomer($customer);

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }

    /**
     * List all accounts belonging to a specific customer.
     */
    public function accounts(string $id): AnonymousResourceCollection
    {
        $customer = Customer::findOrFail($id);
        $accounts = $this->customerService->getCustomerAccounts($customer);

        return AccountResource::collection($accounts);
    }

    /**
     * List all loans belonging to a specific customer.
     */
    public function loans(string $id): AnonymousResourceCollection
    {
        $customer = Customer::findOrFail($id);
        $loans = $this->customerService->getCustomerLoans($customer);

        return LoanResource::collection($loans);
    }
}
