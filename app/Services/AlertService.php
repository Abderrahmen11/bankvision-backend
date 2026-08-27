<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AlertService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'severity'    => 'severity',
        'status'      => 'status',
        'created_at'  => 'created_at',
        'resolved_at' => 'resolved_at',
    ];

    /**
     * Get paginated alerts with search, filters, assigned staff, and polymorphic subjects.
     */
    public function getPaginatedAlerts(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user    = $user ?? auth()->user();

        $query = Alert::query()
            ->with(['assignedTo', 'alertable'])
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && $user->role === 'compliance') {
            // Compliance officers see compliance-related alerts:
            // suspicious transactions, KYC expirations, delinquent loans, high/medium severity, or alerts assigned to them
            $query->where(function ($q) use ($user) {
                $q->whereIn('alert_type', ['suspicious_transaction', 'kyc_expiring', 'loan_delinquent'])
                  ->orWhereIn('severity', ['high', 'medium'])
                  ->orWhere('assigned_to', $user->id);
            });
        } elseif ($user && in_array($user->role, ['manager', 'csr'], true) && $user->branch_id) {
            // Manager & CSR only see alerts originating from or assigned to their branch
            $branchId = $user->branch_id;
            $query->where(function ($q) use ($branchId, $user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('assignedTo', fn ($uq) => $uq->where('branch_id', $branchId))
                  ->orWhere(function ($mQ) use ($branchId) {
                      $mQ->where('alertable_type', Customer::class)
                         ->whereHasMorph('alertable', [Customer::class], fn ($cq) => $cq->where('branch_id', $branchId));
                  })
                  ->orWhere(function ($mQ) use ($branchId) {
                      $mQ->where('alertable_type', Account::class)
                         ->whereHasMorph('alertable', [Account::class], fn ($aq) => $aq->whereHas('customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                  })
                  ->orWhere(function ($mQ) use ($branchId) {
                      $mQ->where('alertable_type', Transaction::class)
                         ->whereHasMorph('alertable', [Transaction::class], fn ($tq) => $tq->whereHas('account.customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                  })
                  ->orWhere(function ($mQ) use ($branchId) {
                      $mQ->where('alertable_type', Loan::class)
                         ->whereHasMorph('alertable', [Loan::class], fn ($lq) => $lq->whereHas('customer', fn ($cq) => $cq->where('branch_id', $branchId)));
                  });
            });
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
     * Find single alert with assignee and triggering entity.
     */
    public function getAlertDetails(string|int $id): Alert
    {
        return Alert::with(['assignedTo', 'alertable'])->findOrFail($id);
    }

    /**
     * Resolve an open or in-progress alert.
     */
    public function resolveAlert(Alert|string|int $alert): Alert
    {
        $alert = $alert instanceof Alert
            ? $alert
            : Alert::whereIn('status', ['open', 'in-progress'])->findOrFail($alert);

        $alert->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }

    /**
     * Assign an alert to a staff member and mark it as in-progress.
     */
    public function assignAlert(Alert|string|int $alert, int $userId): Alert
    {
        $alert = $alert instanceof Alert ? $alert : Alert::findOrFail($alert);

        $alert->update([
            'assigned_to' => $userId,
            'status'      => $alert->status === 'open' ? 'in-progress' : $alert->status,
        ]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }
}
