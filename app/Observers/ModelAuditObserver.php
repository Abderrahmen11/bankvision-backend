<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic audit observer: records created / updated / deleted for any model
 * it is attached to. Passwords and tokens are never stored — a credential
 * change is recorded as "changed".
 *
 * The User model uses a field whitelist so routine writes (last_login_at)
 * do not spam the audit trail.
 */
class ModelAuditObserver
{
    /** Attributes never written to the audit trail. */
    private const HIDDEN = ['password', 'remember_token', 'two_factor_code'];

    /** Attributes that produce no audit noise. */
    private const IGNORED = ['updated_at', 'created_at', 'last_login_at'];

    /**
     * User columns worth auditing. Any other change on the users table is
     * bookkeeping, not a security event.
     */
    private const USER_TRACKED = ['name', 'email', 'phone', 'role', 'branch_id', 'status'];

    public function __construct(
        private array $hideKeys = [],
        private ?array $trackedFields = null,
    ) {}

    public function created(Model $model): void
    {
        AuditService::logModelChange(
            $model,
            AuditAction::Created,
            $this->hideKeys,
            $this->ignoredFor($model),
            $this->trackedFields,
        );
    }

    public function updated(Model $model): void
    {
        // Credential change on the users table → dedicated action, no hash.
        if ($model instanceof User && array_key_exists('password', $model->getChanges())) {
            AuditService::log(
                AuditService::resolveUser(),
                AuditAction::PasswordChanged,
                'users',
                $model->getKey(),
                ['password' => 'changed'],
                ['password' => 'changed'],
            );
        }

        AuditService::logModelChange(
            $model,
            AuditAction::Updated,
            $this->hideKeys,
            $this->ignoredFor($model),
            $this->trackedFields,
        );
    }

    public function deleted(Model $model): void
    {
        AuditService::logModelChange(
            $model,
            AuditAction::Deleted,
            $this->hideKeys,
            $this->ignoredFor($model),
            $this->trackedFields,
        );
    }

    private function ignoredFor(Model $model): array
    {
        // The users table gets routine writes (last_login_at) that must not
        // create audit noise; the whitelist in the constructor filters them.
        return $model instanceof User
            ? array_merge(self::IGNORED, ['last_login_at'])
            : self::IGNORED;
    }
}
