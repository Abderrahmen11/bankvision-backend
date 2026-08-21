<?php

namespace App\Services;

use App\Models\Loan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoanService
{
    /**
     * Get paginated loans with filters and owner customer.
     */
    public function getPaginatedLoans(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Loan::query()
            ->with('customer')
            ->filter($filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find single loan with customer details.
     */
    public function getLoanDetails(string|int $id): Loan
    {
        return Loan::with('customer')->findOrFail($id);
    }

    /**
     * Submit and calculate a new loan application.
     */
    public function applyForLoan(array $data): Loan
    {
        $data['loan_number']         = 'LN-' . date('Y') . '-' . rand(10000, 99999);
        $data['outstanding_balance'] = $data['principal_amount'];
        $data['status']              = 'pending';
        $data['end_date']            = now()->parse($data['start_date'])
            ->addMonths((int) $data['term_months'])
            ->toDateString();
        $data['next_payment_date']   = now()->parse($data['start_date'])
            ->addMonth()
            ->toDateString();

        $loan = Loan::create($data);

        return $loan->load('customer');
    }

    /**
     * Update loan details.
     */
    public function updateLoan(Loan $loan, array $data): Loan
    {
        $loan->update($data);

        return $loan->fresh('customer');
    }

    /**
     * Approve a pending loan.
     */
    public function approveLoan(Loan $loan): Loan
    {
        $loan->update(['status' => 'active']);

        return $loan->fresh('customer');
    }
}
