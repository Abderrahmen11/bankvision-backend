<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy extends BranchScopedPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $this->canAccessBranch($user, $customer->branch_id);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}