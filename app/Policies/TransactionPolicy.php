<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy extends BranchScopedPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return $this->canAccessBranch($user, $transaction->account?->customer?->branch_id);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }
}