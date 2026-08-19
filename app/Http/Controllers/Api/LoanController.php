<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanController extends Controller
{
    /**
     * List loans with optional filters.
     * Filters: customer_id, type, status
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $loans = Loan::query()
            ->with('customer')
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->type,        fn ($q) => $q->where('loan_type', $request->type))
            ->when($request->status,      fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15);

        return LoanResource::collection($loans);
    }

    /**
     * Show a single loan with customer details.
     */
    public function show(string $id): LoanResource
    {
        $loan = Loan::with('customer')
            ->findOrFail($id);

        return LoanResource::make($loan);
    }

    /**
     * Submit a new loan application.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id'      => ['required', 'exists:customers,id'],
            'loan_type'        => ['required', 'in:mortgage,personal,auto,business'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'interest_rate'    => ['required', 'numeric', 'min:0'],
            'term_months'      => ['required', 'integer', 'min:1'],
            'start_date'       => ['required', 'date'],
        ]);

        $validated['loan_number']         = 'LN-' . date('Y') . '-' . rand(10000, 99999);
        $validated['outstanding_balance'] = $validated['principal_amount'];
        $validated['status']              = 'pending';
        $validated['end_date']            = now()->parse($validated['start_date'])
            ->addMonths($validated['term_months'])
            ->toDateString();
        $validated['next_payment_date']   = now()->parse($validated['start_date'])
            ->addMonth()
            ->toDateString();

        $loan = Loan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully.',
            'data'    => LoanResource::make($loan->load('customer')),
        ], 201);
    }

    /**
     * Update loan details (status, balance, next payment date, etc).
     * Validates business status transitions: active -> delinquent -> defaulted -> completed.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        $validated = $request->validate([
            'outstanding_balance' => ['sometimes', 'numeric', 'min:0'],
            'next_payment_date'   => ['nullable', 'date'],
            'status'              => ['sometimes', 'in:pending,active,completed,defaulted,delinquent'],
        ]);

        // 1. If loan was already completed, prohibit further mutations
        if ($loan->status === 'completed' && isset($validated['status']) && $validated['status'] !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed loans cannot be modified or transitioned to other states.',
            ], 422);
        }

        // 2. Validate valid state transitions
        if (isset($validated['status']) && $validated['status'] !== $loan->status) {
            $allowedTransitions = [
                'pending'    => ['active'],
                'active'     => ['delinquent', 'completed'],
                'delinquent' => ['active', 'defaulted', 'completed'],
                'defaulted'  => ['completed'],
                'completed'  => [],
            ];

            $targetStatus = $validated['status'];
            $currentStatus = $loan->status;

            if (!isset($allowedTransitions[$currentStatus]) || !in_array($targetStatus, $allowedTransitions[$currentStatus], true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid status transition from '{$currentStatus}' to '{$targetStatus}'.",
                ], 422);
            }
        }

        // 3. Automatically complete loan if outstanding balance becomes 0
        if (isset($validated['outstanding_balance']) && (float) $validated['outstanding_balance'] === 0.0) {
            $validated['status'] = 'completed';
            $validated['next_payment_date'] = null;
        }

        $loan->update($validated);

        // 4. If loan transitioned to delinquent or defaulted, create compliance alert
        if (in_array($loan->status, ['delinquent', 'defaulted'], true)) {
            $severity = $loan->status === 'defaulted' ? 'high' : 'medium';
            $existingAlert = \App\Models\Alert::where('alertable_type', Loan::class)
                ->where('alertable_id', $loan->id)
                ->where('status', '!=', 'resolved')
                ->first();

            if (!$existingAlert) {
                \App\Models\Alert::create([
                    'alert_number'   => 'ALT-' . date('Y') . '-' . rand(10000, 99999),
                    'alert_type'     => 'loan_delinquent',
                    'severity'       => $severity,
                    'description'    => "Loan {$loan->loan_number} marked as {$loan->status} with balance of {$loan->outstanding_balance}.",
                    'status'         => 'open',
                    'alertable_type' => Loan::class,
                    'alertable_id'   => $loan->id,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Loan updated successfully.',
            'data'    => LoanResource::make($loan->fresh('customer')),
        ]);
    }

    /**
     * Approve a pending loan application (sets status to active).
     */
    public function approve(string $id): JsonResponse
    {
        $loan = Loan::where('status', 'pending')
            ->findOrFail($id);

        $loan->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Loan approved and activated successfully.',
            'data'    => LoanResource::make($loan->fresh('customer')),
        ]);
    }
}
