<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanController extends Controller
{
    public function __construct(
        protected LoanService $loanService
    ) {}

    /**
     * List loans with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $loans = $this->loanService->getPaginatedLoans(
            $request->only(['customer_id', 'type', 'status'])
        );

        return LoanResource::collection($loans);
    }

    /**
     * Show a single loan with details.
     */
    public function show(string $id): LoanResource
    {
        $loan = $this->loanService->getLoanDetails($id);

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

        $loan = $this->loanService->applyForLoan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully.',
            'data'    => LoanResource::make($loan),
        ], 201);
    }

    /**
     * Update loan details.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        $validated = $request->validate([
            'outstanding_balance' => ['sometimes', 'numeric', 'min:0'],
            'next_payment_date'   => ['sometimes', 'date'],
            'status'              => ['sometimes', 'in:pending,active,completed,defaulted,delinquent'],
        ]);

        $updated = $this->loanService->updateLoan($loan, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Loan updated successfully.',
            'data'    => LoanResource::make($updated),
        ]);
    }

    /**
     * Approve a pending loan application.
     */
    public function approve(string $id): JsonResponse
    {
        $loan = Loan::where('status', 'pending')->findOrFail($id);
        $approved = $this->loanService->approveLoan($loan);

        return response()->json([
            'success' => true,
            'message' => 'Loan approved and activated successfully.',
            'data'    => LoanResource::make($approved),
        ]);
    }
}
