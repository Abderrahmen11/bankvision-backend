<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    /**
     * List accounts with optional filters.
     * Filters: customer_id, type, status, currency
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = Account::query()
            ->with('customer')
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->type,        fn ($q) => $q->where('account_type', $request->type))
            ->when($request->status,      fn ($q) => $q->where('status', $request->status))
            ->when($request->currency,    fn ($q) => $q->where('currency', $request->currency))
            ->latest()
            ->paginate(15);

        return AccountResource::collection($accounts);
    }

    /**
     * Show a single account with its customer details.
     */
    public function show(string $id): AccountResource
    {
        $account = Account::with('customer')
            ->findOrFail($id);

        return AccountResource::make($account);
    }

    /**
     * Open a new bank account for a customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id'   => ['required', 'exists:customers,id'],
            'account_type'  => ['required', 'in:savings,checking,business'],
            'currency'      => ['sometimes', 'string', 'size:3'],
            'balance'       => ['sometimes', 'numeric', 'min:0'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0'],
            'opened_date'   => ['sometimes', 'date'],
        ]);

        $validated['account_number'] = 'ACC-' . date('Y') . '-' . rand(10000, 99999);
        $validated['opened_date']    = $validated['opened_date'] ?? date('Y-m-d');
        $validated['status']         = 'active';

        $account = Account::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account opened successfully.',
            'data'    => AccountResource::make($account->load('customer')),
        ], 201);
    }

    /**
     * Update account details (status, interest rate, etc).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $account = Account::findOrFail($id);

        $validated = $request->validate([
            'status'        => ['sometimes', 'in:active,frozen,closed'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $account->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data'    => AccountResource::make($account->fresh('customer')),
        ]);
    }

    /**
     * Close an account (sets status to closed).
     */
    public function destroy(string $id): JsonResponse
    {
        $account = Account::findOrFail($id);
        $account->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Account closed successfully.',
        ]);
    }

    /**
     * List all transactions for a specific account.
     * Filters: type, status, date_from, date_to
     */
    public function transactions(Request $request, string $id): AnonymousResourceCollection
    {
        $account = Account::findOrFail($id);

        $transactions = $account->transactions()
            ->with('approver')
            ->when($request->type,      fn ($q) => $q->where('transaction_type', $request->type))
            ->when($request->status,    fn ($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn ($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->latest('transaction_date')
            ->paginate(15);

        return TransactionResource::collection($transactions);
    }
}
