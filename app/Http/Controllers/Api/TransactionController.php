<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    /**
     * List transactions with filters.
     * Filters: account_id, type, status, date_from, date_to, channel
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = Transaction::query()
            ->with(['account.customer', 'approver'])
            ->when($request->account_id, fn ($q) => $q->where('account_id', $request->account_id))
            ->when($request->type,       fn ($q) => $q->where('transaction_type', $request->type))
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->when($request->channel,    fn ($q) => $q->where('channel', $request->channel))
            ->when($request->date_from,  fn ($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->date_to,    fn ($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->latest('transaction_date')
            ->paginate(15);

        return TransactionResource::collection($transactions);
    }

    /**
     * Show a single transaction with account and approver details.
     */
    public function show(string $id): TransactionResource
    {
        $transaction = Transaction::with(['account.customer', 'approver'])
            ->findOrFail($id);

        return TransactionResource::make($transaction);
    }

    /**
     * Record a new transaction (deposit, withdrawal, or transfer).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id'       => ['required', 'exists:accounts,id'],
            'transaction_type' => ['required', 'in:deposit,withdrawal,transfer,wire'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'currency'         => ['sometimes', 'string', 'size:3'],
            'description'      => ['nullable', 'string'],
            'channel'          => ['sometimes', 'in:online,branch,atm,mobile'],
            'counterparty'     => ['nullable', 'string', 'max:255'],
        ]);

        $validated['transaction_number'] = 'TXN-' . date('Y') . '-' . rand(100000, 999999);
        $validated['transaction_date']   = now();
        $validated['status']             = 'completed';

        $transaction = Transaction::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transaction recorded successfully.',
            'data'    => TransactionResource::make($transaction->load(['account.customer', 'approver'])),
        ], 201);
    }

    /**
     * Approve a flagged transaction.
     * Sets status to 'completed' and records the approver and timestamp.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $transaction = Transaction::where('status', 'flagged')
            ->findOrFail($id);

        $transaction->update([
            'status'      => 'completed',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction approved successfully.',
            'data'    => TransactionResource::make($transaction->fresh(['account.customer', 'approver'])),
        ]);
    }

    /**
     * Flag a transaction as suspicious for compliance review.
     * Generates a high-severity compliance Alert automatically.
     */
    public function flag(string $id): JsonResponse
    {
        $transaction = Transaction::whereIn('status', ['completed', 'pending'])
            ->findOrFail($id);

        DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => 'flagged']);

            // Create compliance Alert if not already existing
            $existingAlert = Alert::where('alertable_type', Transaction::class)
                ->where('alertable_id', $transaction->id)
                ->where('status', '!=', 'resolved')
                ->first();

            if (!$existingAlert) {
                Alert::create([
                    'alert_number'   => 'ALT-' . date('Y') . '-' . rand(10000, 99999),
                    'alert_type'     => 'suspicious_transaction',
                    'severity'       => 'high',
                    'description'    => 'Suspicious transaction ' . $transaction->transaction_number . ' flagged for compliance investigation.',
                    'status'         => 'open',
                    'alertable_type' => Transaction::class,
                    'alertable_id'   => $transaction->id,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Transaction flagged for review.',
            'data'    => TransactionResource::make($transaction->fresh(['account.customer', 'approver'])),
        ]);
    }
}
