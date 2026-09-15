<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

abstract class BranchScopedPolicy
{
    protected function canAccessBranch(User $user, ?int $branchId): bool
    {
        if (! in_array($user->role, Role::branchScoped(), true)) {
            return true;
        }

        return $user->branch_id !== null && $branchId !== null && (int) $user->branch_id === $branchId;
    }
}