<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Models\User;

class KycDocumentPolicy extends BranchScopedPolicy
{
    /**
     * Determine whether the user can list documents for a customer.
     */
    public function viewAny(User $user, Customer $customer): bool
    {
        if (in_array($user->role, [Role::Admin->value, Role::Compliance->value, Role::Analyst->value, Role::Auditor->value], true)) {
            return true;
        }

        if (in_array($user->role, Role::branchScoped(), true)) {
            return $this->canAccessBranch($user, $customer->branch_id);
        }

        return false;
    }

    /**
     * Determine whether the user can view document metadata.
     */
    public function view(User $user, KycDocument $document): bool
    {
        if (in_array($user->role, [Role::Admin->value, Role::Compliance->value, Role::Analyst->value, Role::Auditor->value], true)) {
            return true;
        }

        if (in_array($user->role, Role::branchScoped(), true)) {
            $customer = $document->relationLoaded('customer') ? $document->customer : $document->customer()->first();
            return $this->canAccessBranch($user, $customer?->branch_id);
        }

        return false;
    }

    /**
     * Determine whether the user can download the document file.
     */
    public function download(User $user, KycDocument $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Determine whether the user can upload and verify a document for a customer.
     * Admin, compliance, manager can upload/verify.
     * CSR, analyst, auditor cannot upload.
     */
    public function create(User $user, Customer $customer): bool
    {
        if (in_array($user->role, [Role::Admin->value, Role::Compliance->value], true)) {
            return true;
        }

        if ($user->role === Role::Manager->value) {
            return $this->canAccessBranch($user, $customer->branch_id);
        }

        return false;
    }

    /**
     * Determine whether the user can delete a document (Admin only).
     */
    public function delete(User $user, KycDocument $document): bool
    {
        return $user->role === Role::Admin->value;
    }
}
