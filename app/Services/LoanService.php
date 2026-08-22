<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Loan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    /**
     * Allowed status transitions.
     */
    private const TRANSITIONS = [
        'pending'    => ['active', 'completed'],
        'active'     => ['delinquent', 'completed'],
        'delinquent' => ['defaulted', 'completed'],
        'defaulted'  => ['completed'],
        'completed'  => [],
    ];

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
        $startDate = Carbon::parse($data['start_date']);

        $data['loan_number']         = 'LN-' . date('Y') . '-' . rand(10000, 99999);
        $data['outstanding_balance'] = $data['principal_amount'];
        $data['status']              = 'pending';
        $data['end_date']            = $startDate->copy()->addMonths((int) $data['term_months'])->toDateString();
        $data['next_payment_date']   = $startDate->copy()->addMonth()->toDateString();

        $loan = Loan::create($data);

        return $loan->load('customer');
    }

    /**
     * Update loan details — enforces status transition rules atomically.
     */
    public function updateLoan(Loan|string|int $loan, array $data): Loan
    {
        return DB::transaction(function () use ($loan, $data) {
            $loan = $loan instanceof Loan ? $loan : Loan::findOrFail($loan);

            // Block modifications on completed loans
            if ($loan->status === 'completed') {
                throw ValidationException::withMessages([
                    'message' => 'Completed loans cannot be modified or transitioned to other states.',
                ]);
            }

            // Validate status transition if a new status is provided
            if (isset($data['status']) && $data['status'] !== $loan->status) {
                $allowed = self::TRANSITIONS[$loan->status] ?? [];
                if (! in_array($data['status'], $allowed, true)) {
                    throw ValidationException::withMessages([
                        'message' => "Invalid status transition from '{$loan->status}' to '{$data['status']}'.",
                    ]);
                }

                // Fire compliance alerts for risky transitions
                $this->maybeCreateAlert($loan, $data['status']);
            }

            $loan->update($data);

            // Auto-complete if outstanding balance hits zero
            if (
                isset($data['outstanding_balance']) &&
                (float) $data['outstanding_balance'] <= 0 &&
                $loan->fresh()->status !== 'completed'
            ) {
                $loan->update(['status' => 'completed', 'outstanding_balance' => 0]);
            }

            return $loan->fresh('customer');
        });
    }

    /**
     * Approve a pending loan.
     */
    public function approveLoan(Loan|string|int $loan): Loan
    {
        $loan = $loan instanceof Loan
            ? $loan
            : Loan::where('status', 'pending')->findOrFail($loan);

        $loan->update(['status' => 'active']);

        return $loan->fresh('customer');
    }

    /**
     * Create a compliance alert when a loan becomes delinquent or defaulted.
     */
    private function maybeCreateAlert(Loan $loan, string $newStatus): void
    {
        if ($newStatus === 'delinquent') {
            Alert::create([
                'alertable_type' => Loan::class,
                'alertable_id'   => $loan->id,
                'alert_type'     => 'delinquent_loan',
                'severity'       => 'medium',
                'status'         => 'open',
                'description'    => "Loan #{$loan->loan_number} has become delinquent.",
            ]);
        } elseif ($newStatus === 'defaulted') {
            Alert::create([
                'alertable_type' => Loan::class,
                'alertable_id'   => $loan->id,
                'alert_type'     => 'defaulted_loan',
                'severity'       => 'high',
                'status'         => 'open',
                'description'    => "Loan #{$loan->loan_number} has defaulted.",
            ]);
        }
    }
}
