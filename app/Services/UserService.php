<?php

namespace App\Services;

use App\Models\User;
use App\Enums\Role;
use App\Support\BranchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserService
{
    /** Sortable columns and their actual DB column names. */
    private const SORT_MAP = [
        'name'          => 'name',
        'role'          => 'role',
        'created_at'    => 'created_at',
        'last_login_at' => 'last_login_at',
    ];

    /**
     * Get paginated user list with search, filters, sorting, and eager-loaded branch.
     */
    public function getPaginatedUsers(array $filters = [], ?int $perPage = null, ?User $user = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? $perPage ?? 15);
        $user    = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);

        $query = User::query()
            ->with('branch')
            ->filter($filters);

        // Role-based restrictions hook
        if ($user && $user->role === Role::Manager->value && $user->branch_id) {
            // Manager only sees employees belonging to their assigned branch
            $query->where('branch_id', $user->branch_id);
        }
        // Admin, Analyst, Auditor, Compliance: full bank-wide read access — no additional restriction applied

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
     * Find single user with branch details.
     */
    public function getUserDetails(string|int $id, ?User $user = null): User
    {
        $user = $user ?? auth()->user();
        if ($user) BranchScope::ensure($user);
        $targetUser = User::with('branch')->findOrFail($id);

        if ($user && $user->role === Role::Manager->value && $user->branch_id) {
            if ((int) $targetUser->branch_id !== (int) $user->branch_id) {
                throw new AccessDeniedHttpException('Access forbidden. User does not belong to your assigned branch.');
            }
        }

        return $targetUser;
    }

    /**
     * Create a new user record.
     */
    public function createUser(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $data['status'] = $data['status'] ?? 'active';

        $user = User::create($data);

        return $user->load('branch');
    }

    /**
     * Update an existing user.
     */
    public function updateUser(User|string|int $user, array $data): User
    {
        $user = $user instanceof User ? $user : User::findOrFail($user);

        if (isset($data['password']) && filled($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user->fresh('branch');
    }

    /**
     * Return users eligible to be assigned as relationship managers for a branch.
     *
     * Eligible means: role in [csr, manager], status = active, branch_id = $branchId.
     * The $requestingUser guard is applied upstream (route middleware), so by the time
     * this method runs the caller is already allowed to query this branch.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function getEligibleRelationshipManagers(int $branchId): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->where('branch_id', $branchId)
            ->whereIn('role', [Role::Manager->value, Role::Csr->value])
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    /**
     * Delete a user record, preventing deleting own account.
     */
    public function deleteUser(User|string|int $user, ?User $authenticatedUser = null): bool
    {
        $user = $user instanceof User ? $user : User::findOrFail($user);
        $authenticatedUser = $authenticatedUser ?? auth()->user();

        if ($authenticatedUser && (int) $authenticatedUser->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'message' => 'You cannot delete your own account.',
            ]);
        }

        return (bool) $user->delete();
    }
}
