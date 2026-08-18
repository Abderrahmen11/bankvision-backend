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
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        $validated = $request->validate([
            'outstanding_balance' => ['sometimes', 'numeric', 'min:0'],
            'next_payment_date'   => ['sometimes', 'date'],
            'status'              => ['sometimes', 'in:pending,active,completed,defaulted,delinquent'],
        ]);

        $loan->update($validated);

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
