<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * List accounts with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $this->accountService->getPaginatedAccounts(
            $request->only(['customer_id', 'type', 'status', 'currency'])
        );

        return AccountResource::collection($accounts);
    }

    /**
     * Show a single account.
     */
    public function show(string $id): AccountResource
    {
        $account = $this->accountService->getAccountDetails($id);

        return AccountResource::make($account);
    }

    /**
     * Open a new bank account.
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = $this->accountService->openAccount($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Account opened successfully.',
            'data'    => AccountResource::make($account),
        ], 201);
    }

    /**
     * Update account details.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $account = Account::findOrFail($id);

        $validated = $request->validate([
            'status'        => ['sometimes', 'in:active,frozen,closed'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $updated = $this->accountService->updateAccount($account, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data'    => AccountResource::make($updated),
        ]);
    }

    /**
     * Close an account.
     */
    public function destroy(string $id): JsonResponse
    {
        $account = Account::findOrFail($id);
        $this->accountService->closeAccount($account);

        return response()->json([
            'success' => true,
            'message' => 'Account closed successfully.',
        ]);
    }

    /**
     * List all transactions for a specific account.
     */
    public function transactions(Request $request, string $id): AnonymousResourceCollection
    {
        $account = Account::findOrFail($id);
        $transactions = $this->accountService->getAccountTransactions(
            $account,
            $request->only(['type', 'status', 'date_from', 'date_to'])
        );

        return TransactionResource::collection($transactions);
    }
}
