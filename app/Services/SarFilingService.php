<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\SarFiling;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SarFilingService
{
    /**
     * Paginated SAR registry with search and status filters, newest first.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null, page?: int|null}  $filters
     */
    public function getPaginatedFilings(array $filters = [], ?int $perPage = null, ?User $caller = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $caller  = $caller ?? auth()->user();

        $query = SarFiling::query()
            ->with('user:id,name,role')
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->orderByDesc('created_at');

        // Managers may only view SAR filings that were raised against subjects
        // belonging to their branch (prevents cross-branch data leakage).
        // SAR filings store a denormalized customer_number string; we join to
        // the customers table on that field to apply the branch constraint.
        if ($caller && $caller->role === Role::Manager->value && $caller->branch_id) {
            $branchId = (int) $caller->branch_id;
            $query->where(function ($q) use ($branchId, $caller) {
                // Filed against a customer whose branch matches the manager's branch
                $q->whereIn('customer_number',
                    Customer::where('branch_id', $branchId)->pluck('customer_number')
                )
                // OR the manager filed the SAR themselves (no customer_number recorded)
                ->orWhere(function ($q2) use ($caller) {
                    $q2->whereNull('customer_number')
                       ->where('user_id', $caller->id);
                });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Record a new Suspicious Activity Report.
     *
     * @param  array{customer_name: string, customer_number?: string|null, category: string, amount: int|float|string, status?: string, narrative: string, action_taken?: string|null, alert_id?: int|null}  $data
     */
    public function createFiling(array $data, User $filer): SarFiling
    {
        if (isset($data['category']) && ! in_array($data['category'], SarFiling::CATEGORIES, true)) {
            throw ValidationException::withMessages([
                'category' => 'Selected category is not a sanctioned suspicious-activity category.',
            ]);
        }

        // If an alert is referenced, it must exist and belong to the same subject context
        if (! empty($data['alert_id'])) {
            $alert = Alert::with('alertable')->findOrFail($data['alert_id']);
            $alertable = $alert->alertable;

            if ($filer->role === Role::Manager->value && $filer->branch_id === null) {
                throw new AccessDeniedHttpException('Access forbidden. Filer is not assigned to a branch.');
            }

            if ($filer->role === Role::Manager->value && ! $this->alertBelongsToBranch($alertable, (int) $filer->branch_id)) {
                throw new AccessDeniedHttpException('Access forbidden. Alert is outside the filer branch.');
            }

            if ($data['customer_number'] ?? null) {
                $alertCustomerNumber = match (true) {
                    $alertable instanceof Customer => $alertable->customer_number,
                    $alertable instanceof Account => $alertable->customer?->customer_number,
                    $alertable instanceof Transaction => $alertable->account?->customer?->customer_number,
                    $alertable instanceof Loan => $alertable->customer?->customer_number,
                    default => null,
                };

                if ($alertCustomerNumber !== $data['customer_number']) {
                    throw ValidationException::withMessages([
                        'alert_id' => 'Alert does not belong to the reported customer.',
                    ]);
                }
            }
        }

        return SarFiling::create([
            ...$data,
            'user_id' => $filer->id,
        ]);
    }

    /**
     * Whether the given user may record new SAR filings.
     */
    public static function canFile(User $user): bool
    {
        return in_array($user->role, Role::flaggers(), true);
    }

    private function alertBelongsToBranch(mixed $alertable, int $branchId): bool
    {
        return match (true) {
            $alertable instanceof Customer => (int) $alertable->branch_id === $branchId,
            $alertable instanceof Account => (int) $alertable->customer?->branch_id === $branchId,
            $alertable instanceof Transaction => (int) $alertable->account?->customer?->branch_id === $branchId,
            $alertable instanceof Loan => (int) $alertable->customer?->branch_id === $branchId,
            default => false,
        };
    }
}
