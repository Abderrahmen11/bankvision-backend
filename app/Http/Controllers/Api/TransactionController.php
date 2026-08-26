<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * List transactions with search, filters, sorting, and pagination.
     */
    public function index(IndexTransactionRequest $request): AnonymousResourceCollection
    {
        $transactions = $this->transactionService->getPaginatedTransactions(
            $request->validated(),
            user: $request->user()
        );

        return TransactionResource::collection($transactions);
    }

    /**
     * Show a single transaction.
     */
    public function show(string $id): TransactionResource
    {
        $transaction = $this->transactionService->getTransactionDetails($id);

        return TransactionResource::make($transaction);
    }

    /**
     * Record a new transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $transaction = $this->transactionService->recordTransaction($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Transaction recorded successfully.',
            'data'    => TransactionResource::make($transaction),
        ], 201);
    }

    /**
     * Approve a flagged transaction.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $approved = $this->transactionService->approveTransaction($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Transaction approved successfully.',
            'data'    => TransactionResource::make($approved),
        ]);
    }

    /**
     * Flag a transaction as suspicious.
     */
    public function flag(string $id): JsonResponse
    {
        $flagged = $this->transactionService->flagTransaction($id);

        return response()->json([
            'success' => true,
            'message' => 'Transaction flagged for review.',
            'data'    => TransactionResource::make($flagged),
        ]);
    }
}
