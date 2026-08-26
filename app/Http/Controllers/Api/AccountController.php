<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\IndexAccountRequest;
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
     * List accounts with search, filters, sorting, and pagination.
     */
    public function index(IndexAccountRequest $request): AnonymousResourceCollection
    {
        $accounts = $this->accountService->getPaginatedAccounts(
            $request->validated(),
            user: $request->user()
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
    public function update(UpdateAccountRequest $request, string $id): JsonResponse
    {
        $updated = $this->accountService->updateAccount($id, $request->validated());

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
        $this->accountService->closeAccount($id);

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
        $transactions = $this->accountService->getAccountTransactions(
            $id,
            $request->only(['type', 'status', 'date_from', 'date_to'])
        );

        return TransactionResource::collection($transactions);
    }
}
