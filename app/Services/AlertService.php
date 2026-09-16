<?php

namespace App\Services;

use App\Models\Account;
use App\Support\DashboardCache;use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Notification;
use App\Enums\Role;
use App\Support\BranchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
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
        if ($user) BranchScope::ensure($user);

        $query = Alert::query()
            ->with(['assignedTo', 'alertable'])
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && $user->role === Role::Analyst->value) {
            // Risk analyst: only risk-related alerts
            $query->where(function ($q) {
                $q->whereIn('alert_type', ['suspicious_transaction', 'loan_delinquent', 'defaulted_loan'])
                  ->orWhere('severity', 'high');
            });
        } elseif ($user && $user->role === Role::Csr->value && $user->branch_id) {
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
        } elseif ($user && $user->role === Role::Manager->value && $user->branch_id) {
            // Manager only see alerts originating from their branch
            $branchId = $user->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where(function ($mQ) use ($branchId) {
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
                  })
                  ->orWhere(function ($mQ) use ($branchId) {
                      $mQ->whereNull('alertable_type')
                         ->whereHas('assignedTo', fn ($uq) => $uq->where('branch_id', $branchId));
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
        if ($user) BranchScope::ensure($user);
        $alert = Alert::with(['assignedTo', 'alertable'])->findOrFail($id);

        if ($user && $user->role === Role::Manager->value && $user->branch_id) {
            if (! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. Alert does not belong to your assigned branch.');
            }
        }

        if ($user && $user->role === Role::Csr->value && $user->branch_id) {
            $isCustomerRelated = $alert->alertable_type === Customer::class || $alert->alert_type === 'kyc_expiring';
            if (! $isCustomerRelated || ! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. CSR can only view customer-related alerts for their assigned branch.');
            }
        }

        if ($user && $user->role === Role::Analyst->value) {
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
        if ($user) BranchScope::ensure($user);
        $alert = $alert instanceof Alert
            ? $alert
            : Alert::with(['assignedTo', 'alertable'])->whereIn('status', ['open', 'in-progress'])->findOrFail($alert);

        if ($user && $user->role === Role::Manager->value && $user->branch_id) {
            if (! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot resolve alert outside your assigned branch.');
            }
        }

        $alert->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        DashboardCache::flushRecentActivity();

        return $alert->fresh(['assignedTo', 'alertable']);
    }

    /**
     * Alias for assignAlert to support assign() calls.
     */
    public function assign(Alert|string|int $alert, int $userId, ?User $user = null): Alert
    {
        return $this->assignAlert($alert, $userId, $user);
    }

    /**
     * Assign an alert to a staff member and mark it as in-progress.
     */
    public function assignAlert(Alert|string|int $alert, int $userId, ?User $user = null): Alert
    {
        $user = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);
        $alert = $alert instanceof Alert ? $alert : Alert::with(['assignedTo', 'alertable'])->findOrFail($alert);

        if ($user && $user->role === Role::Manager->value && $user->branch_id) {
            if (! $this->alertBelongsToBranch($alert, (int) $user->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. Cannot assign alert outside your assigned branch.');
            }
        }

        $assignee = User::findOrFail($userId);
        if ($assignee->status !== 'active') {
            throw new AccessDeniedHttpException('Access forbidden. Alerts can only be assigned to active staff.');
        }
        if ($user && $user->role === Role::Manager->value && (int) $assignee->branch_id !== (int) $user->branch_id) {
            throw new AccessDeniedHttpException('Access forbidden. Cannot assign an alert outside your assigned branch.');
        }

        $entityBranchId = $this->getEntityBranchId($alert);
        if ($assignee->role === Role::Manager->value && $entityBranchId && (int) $assignee->branch_id !== (int) $entityBranchId) {
            throw new AccessDeniedHttpException('Access forbidden. Cannot assign a manager from a different branch.');
        }
        if ($user && in_array($user->role, Role::branchScoped(), true) && $entityBranchId && (int) $assignee->branch_id !== (int) $entityBranchId) {
            throw new AccessDeniedHttpException('Access forbidden. Cannot assign staff from a different branch.');
        }

        $alert->update([
            'assigned_to' => $userId,
            'status'      => $alert->status === 'open' ? 'in-progress' : $alert->status,
        ]);

        DashboardCache::flushRecentActivity();

        $this->notifyAssignment($alert->fresh(['assignedTo', 'alertable']), $user);

        return $alert->fresh(['assignedTo', 'alertable']);
    }

    /**
     * Notify the assignee that an alert has been assigned to them:
     * in-app notification (bell icon) + email when their preferences allow it.
     */
    private function notifyAssignment(Alert $alert, ?User $actor): void
    {
        $assignee = $alert->assignedTo;
        if (!$assignee) {
            return;
        }

        $title = sprintf('Alert %s assigned to you', $alert->alert_number);
        $message = $alert->description ?: ucfirst(str_replace('_', ' ', $alert->alert_type));
        $link = '/alerts/' . $alert->id;

        Notification::announce(
            $assignee->id,
            $title,
            $message,
            $link,
            in_array($alert->severity, ['critical', 'high'], true) ? 'warning' : 'info'
        );

        // Email only if the assignee opted into email notifications
        $prefs = $assignee->settings()->first()?->notifications() ?? [];
        if (!empty($prefs['email_notifications'])) {
            try {
                Mail::raw(
                    "Hello {$assignee->name},\n\n{$title}.\n\n{$message}\n\nOpen it in BankVision: {$link}\n\n- BankVision Alert System",
                    function ($mail) use ($assignee, $title) {
                        $mail->to($assignee->email)->subject("[BankVision] {$title}");
                    }
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Derive the branch_id of the entity this alert is about.
     */
    public function getEntityBranchId(Alert $alert): ?int
    {
        $alertable = $alert->alertable;
        if ($alertable instanceof Customer) {
            return (int) $alertable->branch_id;
        }
        if ($alertable instanceof Account) {
            return (int) $alertable->customer?->branch_id;
        }
        if ($alertable instanceof Transaction) {
            return (int) $alertable->account?->customer?->branch_id;
        }
        if ($alertable instanceof Loan) {
            return (int) $alertable->customer?->branch_id;
        }

        return null;
    }

    /**
     * Check if alert is associated with a given branch.
     */
    private function alertBelongsToBranch(Alert $alert, int $branchId): bool
    {
        $entityBranch = $this->getEntityBranchId($alert);
        if ($entityBranch !== null) {
            return $entityBranch === $branchId;
        }

        if ($alert->assignedTo && (int) $alert->assignedTo->branch_id === $branchId) {
            return true;
        }

        return false;
    }
}
