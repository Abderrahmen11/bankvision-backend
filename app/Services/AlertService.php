<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        if ($user && $user->role === 'analyst') {
            // Risk analyst: only risk-related alerts
            $query->where(function ($q) {
                $q->whereIn('alert_type', ['suspicious_transaction', 'loan_delinquent', 'defaulted_loan'])
                  ->orWhere('severity', 'high');
            });
        } elseif ($user && $user->role === 'csr' && $user->branch_id) {
            // CSR: only customer-related alerts in their branch
            $branchId = $user->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where(function ($cq) use ($branchId) {
                    $cq->where('alertable_type', Customer::class)
                       ->whereHasMorph('alertable', [Customer::class], fn ($c) => $c->where('branch_id', $branchId));
                })->orWhere(function ($kq) use ($branchId) {
                    $kq->where('alert_type', 'kyc_expiring')
                       ->whereHasMorph('alertable', [Customer::class], fn ($c) => $c->where('branch_id', $branchId));
                });
            });
        } elseif ($user && in_array($user->role, ['manager'], true) && $user->branch_id) {
            // Manager only see alerts originating from or assigned to their branch
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
        // Compliance, Admin, Auditor: full access to alerts

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
    public function getAlertDetails(string|int $id, ?User $user = null): Alert
    {
        $user = $user ?? auth()->user();
        $alert = Alert::with(['assignedTo', 'alertable'])->findOrFail($id);

        if ($user && $user->role === 'manager' && $user->branch_id) {
            if (! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. Alert does not belong to your assigned branch.');
            }
        }

        if ($user && $user->role === 'csr' && $user->branch_id) {
            $isCustomerRelated = $alert->alertable_type === Customer::class || $alert->alert_type === 'kyc_expiring';
            if (! $isCustomerRelated || ! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. CSR can only view customer-related alerts for their assigned branch.');
            }
        }

        if ($user && $user->role === 'analyst') {
            $isRiskRelated = in_array($alert->alert_type, ['suspicious_transaction', 'loan_delinquent', 'defaulted_loan'], true) || $alert->severity === 'high';
            if (! $isRiskRelated) {
                throw new AccessDeniedHttpException('Access forbidden. Risk Analysts can only view risk-related alerts.');
            }
        }

        return $alert;
    }

    /**
     * Resolve an open or in-progress alert.
     */
    public function resolveAlert(Alert|string|int $alert, ?User $user = null): Alert
    {
        $user = $user ?? auth()->user();
        $alert = $alert instanceof Alert
            ? $alert
            : Alert::with(['assignedTo', 'alertable'])->whereIn('status', ['open', 'in-progress'])->findOrFail($alert);

        if ($user && $user->role === 'manager' && $user->branch_id) {
            if (! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot resolve alert outside your assigned branch.');
            }
        }

        $alert->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }

    /**
     * Assign an alert to a staff member and mark it as in-progress.
     */
    public function assignAlert(Alert|string|int $alert, int $userId, ?User $user = null): Alert
    {
        $user = $user ?? auth()->user();
        $alert = $alert instanceof Alert ? $alert : Alert::with(['assignedTo', 'alertable'])->findOrFail($alert);

        if ($user && $user->role === 'manager' && $user->branch_id) {
            if (! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot assign alert outside your assigned branch.');
            }
        }

        $alert->update([
            'assigned_to' => $userId,
            'status'      => $alert->status === 'open' ? 'in-progress' : $alert->status,
        ]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }

    /**
     * Check if alert is associated with a given branch.
     */
    private function alertBelongsToBranch(Alert $alert, int $branchId): bool
    {
        if ($alert->assignedTo && (int) $alert->assignedTo->branch_id === $branchId) {
            return true;
        }

        $alertable = $alert->alertable;
        if ($alertable instanceof Customer) {
            return (int) $alertable->branch_id === $branchId;
        }
        if ($alertable instanceof Account) {
            return (int) $alertable->customer?->branch_id === $branchId;
        }
        if ($alertable instanceof Transaction) {
            return (int) $alertable->account?->customer?->branch_id === $branchId;
        }
        if ($alertable instanceof Loan) {
            return (int) $alertable->customer?->branch_id === $branchId;
        }

        return false;
    }
}
