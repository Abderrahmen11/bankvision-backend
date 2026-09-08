<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuditLogService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'created_at' => 'created_at',
        'action'     => 'action',
        'table_name' => 'table_name',
        'record_id'  => 'record_id',
        'ip_address' => 'ip_address',
    ];

    /**
     * Get paginated audit logs with search, filters, sorting, and user relation.
     */
    public function getPaginatedLogs(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user    = $user ?? auth()->user();

        $query = AuditLog::query()
            ->with('user')
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && $user->role === 'compliance') {
            // Compliance officers see audit logs for compliance-relevant domain tables and actions
            $query->where(function ($q) {
                $q->whereIn('table_name', ['customers', 'accounts', 'transactions', 'loans', 'alerts'])
                  ->orWhereIn('action', ['approve', 'reject', 'flag', 'freeze', 'update', 'delete']);
            });
        } elseif ($user && $user->role === 'manager' && $user->branch_id) {
            // Branch manager only sees audit logs for staff in their branch
            $query->whereHas('user', fn ($q) => $q->where('branch_id', $user->branch_id));
        }
        // Auditors & Admin: full bank-wide read access — no additional restrictions applied

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
     * Find single audit log with user details.
     */
    public function getLogDetails(string|int $id, ?User $user = null): AuditLog
    {
        $user = $user ?? auth()->user();
        $log = AuditLog::with('user')->findOrFail($id);

        if ($user && $user->role === 'manager' && $user->branch_id) {
            if (! $log->user || (int) $log->user->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. Audit log does not belong to your assigned branch.');
            }
        }

        if ($user && $user->role === 'compliance') {
            $isComplianceRelevant = in_array($log->table_name, ['customers', 'accounts', 'transactions', 'loans', 'alerts'], true)
                || in_array($log->action, ['approve', 'reject', 'flag', 'freeze', 'update', 'delete'], true);

            if (! $isComplianceRelevant) {
                throw new AccessDeniedHttpException('Access forbidden. Compliance officers may only view compliance-relevant audit logs.');
            }
        }

        return $log;
    }
}
