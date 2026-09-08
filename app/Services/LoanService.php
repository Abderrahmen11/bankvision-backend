<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LoanService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'principal_amount'    => 'principal_amount',
        'outstanding_balance' => 'outstanding_balance',
        'interest_rate'       => 'interest_rate',
        'start_date'          => 'start_date',
        'end_date'            => 'end_date',
        'next_payment_date'   => 'next_payment_date',
        'created_at'          => 'created_at',
    ];

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
     * Get paginated loans with search, filters, sorting, and eager-loaded relations.
     */
    public function getPaginatedLoans(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user    = $user ?? auth()->user();

        $query = Loan::query()
            ->with('customer.branch')
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && $user->role === 'compliance') {
            // Compliance officers only see delinquent or defaulted loans
            $query->whereIn('status', ['delinquent', 'defaulted']);
        } elseif ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            // Manager & CSR only see loans belonging to customers in their branch
            $query->whereHas('customer', fn ($q) => $q->where('branch_id', $user->branch_id));
        }

        $sortColumn    = self::SORT_MAP[$filters['sort_by'] ?? ''] ?? null;
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc');
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    /**
     * Find single loan with customer details.
     */
    public function getLoanDetails(string|int $id, ?User $user = null): Loan
    {
        $user = $user ?? auth()->user();
        $loan = Loan::with('customer.branch')->findOrFail($id);

        if ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            if ((int) $loan->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Loan does not belong to your assigned branch.');
            }
        }

        if ($user && $user->role === 'compliance') {
            if (! in_array($loan->status, ['delinquent', 'defaulted'], true)) {
                throw new AccessDeniedHttpException('Access forbidden. Compliance officers may only view delinquent or defaulted loans.');
            }
        }

        return $loan;
    }

    /**
     * Submit and calculate a new loan application.
     */
    public function applyForLoan(array $data, ?User $user = null): Loan
    {
        $user = $user ?? auth()->user();

        if ($user && $user->role === 'csr') {
            throw new AccessDeniedHttpException('Access forbidden. CSR cannot create loans.');
        }

        if ($user && $user->role === 'manager' && $user->branch_id) {
            $customer = Customer::findOrFail($data['customer_id']);
            if ((int) $customer->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot create loan for customer outside your assigned branch.');
            }
        }

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
    public function updateLoan(Loan|string|int $loan, array $data, ?User $user = null): Loan
    {
        $user = $user ?? auth()->user();

        if ($user && $user->role === 'csr') {
            throw new AccessDeniedHttpException('Access forbidden. CSR cannot update loans.');
        }

        return DB::transaction(function () use ($loan, $data, $user) {
            $loan = $loan instanceof Loan ? $loan : Loan::with('customer')->findOrFail($loan);

            if ($user && $user->role === 'manager' && $user->branch_id) {
                if ((int) $loan->customer?->branch_id !== (int) $user->branch_id) {
                    throw new AccessDeniedHttpException('Access forbidden. Cannot update loan outside your assigned branch.');
                }
            }

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
    public function approveLoan(Loan|string|int $loan, ?User $user = null): Loan
    {
        $user = $user ?? auth()->user();

        if ($user && $user->role === 'compliance') {
            throw new AccessDeniedHttpException('Access forbidden. Compliance officers cannot approve loans.');
        }

        $loan = $loan instanceof Loan
            ? $loan
            : Loan::with('customer')->where('status', 'pending')->findOrFail($loan);

        if ($user && $user->role === 'manager' && $user->branch_id) {
            if ((int) $loan->customer?->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot approve loan outside your assigned branch.');
            }
        }

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
