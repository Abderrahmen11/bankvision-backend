<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\IndexCustomerRequest;
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
    ) {
    }

    /**
     * List customers with search, filters, sorting, and pagination.
     */
    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {
        $customers = $this->customerService->getPaginatedCustomers(
            $request->validated(),
            user: $request->user()
        );

        return CustomerResource::collection($customers);
    }

    /**
     * Show a single customer with details.
     */
    public function show(Request $request, string $id): CustomerResource
    {
        $customer = $this->customerService->getCustomerDetails($id, $request->user());

        return CustomerResource::make($customer);
    }

    /**
     * Create a new customer record.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->createCustomer($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => CustomerResource::make($customer),
        ], 201);
    }

    /**
     * Update an existing customer.
     */
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        $updated = $this->customerService->updateCustomer($id, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data' => CustomerResource::make($updated),
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
    public function accounts(Request $request, string $id): AnonymousResourceCollection
    {
        $accounts = $this->customerService->getCustomerAccounts($id, 15, $request->user());

        return AccountResource::collection($accounts);
    }

    /**
     * List all loans belonging to a specific customer.
     */
    public function loans(Request $request, string $id): AnonymousResourceCollection
    {
        $loans = $this->customerService->getCustomerLoans($id, 15, $request->user());

        return LoanResource::collection($loans);
    }
}
