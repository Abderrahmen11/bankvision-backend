<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Enums\Role;
use App\Support\BranchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BranchService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'branch_name' => 'branch_name',
        'branch_code' => 'branch_code',
        'city'        => 'city',
        'created_at'  => 'created_at',
    ];

    /**
     * Get paginated branches with search, filters, sorting, and manager relation.
     */
    public function getPaginatedBranches(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user    = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);

        $query = Branch::query()
            ->with('manager')
            ->withCount('users')
            ->filter($filters);

        // Branch Managers and CSRs see only their assigned branch
        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            $query->where('id', $user->branch_id);
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
     * Find single branch with manager.
     */
    public function getBranchDetails(string|int $id, ?User $user = null): Branch
    {
        $user = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);

        if ($user && in_array($user->role, Role::branchScoped(), true) && $user->branch_id) {
            if ((int) $id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. You may only access your assigned branch.');
            }
        }

        return Branch::with('manager')
            ->withCount('users')
            ->findOrFail($id);
    }

    /**
     * Create a new branch.
     */
    public function createBranch(array $data): Branch
    {
        $branch = Branch::create($data);

        return $branch->load('manager');
    }

    /**
     * Update an existing branch.
     */
    public function updateBranch(Branch|string|int $branch, array $data): Branch
    {
        $branch = $branch instanceof Branch ? $branch : Branch::findOrFail($branch);
        $branch->update($data);

        return $branch->fresh('manager');
    }

    /**
     * Delete a branch if no users or customers are attached.
     */
    public function deleteBranch(Branch|string|int $branch): bool
    {
        $branch = $branch instanceof Branch ? $branch : Branch::findOrFail($branch);

        if ($branch->users()->exists() || $branch->customers()->exists()) {
            throw ValidationException::withMessages([
                'message' => 'Cannot delete a branch that has assigned employees or customers.',
            ]);
        }

        return (bool) $branch->delete();
    }
}
