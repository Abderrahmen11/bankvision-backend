<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy extends BranchScopedPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $this->canAccessBranch($user, $account->customer?->branch_id);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->view($user, $account);
    }
}