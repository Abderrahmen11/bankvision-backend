<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class BranchService
{
    /**
     * Get paginated branches with search and filters.
     */
    public function getPaginatedBranches(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Branch::query()
            ->with('manager')
            ->withCount('users')
            ->filter($filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find single branch with manager.
     */
    public function getBranchDetails(string|int $id): Branch
    {
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
