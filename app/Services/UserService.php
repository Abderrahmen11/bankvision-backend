<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

        $query = User::query()
            ->with('branch')
            ->filter($filters);

        // Role-based restrictions hook (extensible for future role-scoping without separate endpoints)

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
    public function getUserDetails(string|int $id): User
    {
        return User::with('branch')->findOrFail($id);
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
