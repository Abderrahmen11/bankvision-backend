<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BranchScope
{
    public static function ensure(User $user): void
    {
        if (in_array($user->role, Role::branchScoped(), true) && $user->branch_id === null) {
            throw new AccessDeniedHttpException('Access forbidden. Your account is not assigned to a branch.');
        }
    }
}