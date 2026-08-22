<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LoanResource;
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
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->createCustomer($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data'    => CustomerResource::make($customer),
        ], 201);
    }

    /**
     * Update an existing customer.
     */
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        $updated = $this->customerService->updateCustomer($id, $request->validated());

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
        $this->customerService->deleteCustomer($id);

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
        $accounts = $this->customerService->getCustomerAccounts($id);

        return AccountResource::collection($accounts);
    }

    /**
     * List all loans belonging to a specific customer.
     */
    public function loans(string $id): AnonymousResourceCollection
    {
        $loans = $this->customerService->getCustomerLoans($id);

        return LoanResource::collection($loans);
    }
}
