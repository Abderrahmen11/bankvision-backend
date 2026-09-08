<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\IndexLoanRequest;
use App\Http\Requests\Loan\StoreLoanRequest;
use App\Http\Requests\Loan\UpdateLoanRequest;
use App\Http\Resources\LoanResource;
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
     * List loans with search, filters, sorting, and pagination.
     */
    public function index(IndexLoanRequest $request): AnonymousResourceCollection
    {
        $loans = $this->loanService->getPaginatedLoans(
            $request->validated(),
            user: $request->user()
        );

        return LoanResource::collection($loans);
    }

    /**
     * Show a single loan with details.
     */
    public function show(Request $request, string $id): LoanResource
    {
        $loan = $this->loanService->getLoanDetails($id, $request->user());

        return LoanResource::make($loan);
    }

    /**
     * Submit a new loan application.
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $loan = $this->loanService->applyForLoan($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully.',
            'data'    => LoanResource::make($loan),
        ], 201);
    }

    /**
     * Update loan details.
     */
    public function update(UpdateLoanRequest $request, string $id): JsonResponse
    {
        $updated = $this->loanService->updateLoan($id, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Loan updated successfully.',
            'data'    => LoanResource::make($updated),
        ]);
    }

    /**
     * Approve a pending loan application.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $approved = $this->loanService->approveLoan($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Loan approved and activated successfully.',
            'data'    => LoanResource::make($approved),
        ]);
    }
}
