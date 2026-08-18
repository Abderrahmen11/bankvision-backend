<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\AccountResource;
use App\Http\Resources\LoanResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * List customers with optional search and filters.
     * Filters: search (name/email/number), type, kyc_status, risk_level
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->with(['branch', 'relationshipManager'])
            ->withCount(['accounts', 'loans'])
            ->when($request->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('full_name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('customer_number', 'like', "%{$request->search}%")
                )
            )
            ->when($request->type,       fn ($q) => $q->where('customer_type', $request->type))
            ->when($request->kyc_status, fn ($q) => $q->where('kyc_status', $request->kyc_status))
            ->when($request->risk_level, fn ($q) => $q->where('risk_level', $request->risk_level))
            ->latest()
            ->paginate(15);

        return CustomerResource::collection($customers);
    }

    /**
     * Show a single customer with accounts, loans, branch, and manager.
     */
    public function show(string $id): CustomerResource
    {
        $customer = Customer::with(['branch', 'relationshipManager'])
            ->withCount(['accounts', 'loans'])
            ->findOrFail($id);

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

        $validated['customer_number'] = 'CUST-' . date('Y') . '-' . rand(10000, 99999);

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data'    => CustomerResource::make($customer->load(['branch', 'relationshipManager'])),
        ], 201);
    }

    /**
     * Update an existing customer's details.
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

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data'    => CustomerResource::make($customer->fresh(['branch', 'relationshipManager'])),
        ]);
    }

    /**
     * Delete a customer record.
     */
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

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

        $accounts = $customer->accounts()
            ->latest()
            ->paginate(15);

        return AccountResource::collection($accounts);
    }

    /**
     * List all loans belonging to a specific customer.
     */
    public function loans(string $id): AnonymousResourceCollection
    {
        $customer = Customer::findOrFail($id);

        $loans = $customer->loans()
            ->latest()
            ->paginate(15);

        return LoanResource::collection($loans);
    }
}
