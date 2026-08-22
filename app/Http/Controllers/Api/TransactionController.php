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
     * List transactions with filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = $this->transactionService->getPaginatedTransactions(
            $request->only(['account_id', 'type', 'status', 'channel', 'date_from', 'date_to'])
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
        $transaction = Transaction::where('status', 'flagged')->findOrFail($id);
        $approved = $this->transactionService->approveTransaction($transaction, $request->user());

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
        $transaction = Transaction::where('status', 'completed')->findOrFail($id);
        $flagged = $this->transactionService->flagTransaction($transaction);

        return response()->json([
            'success' => true,
            'message' => 'Transaction flagged for review.',
            'data'    => TransactionResource::make($flagged),
        ]);
    }
}
