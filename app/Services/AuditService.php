<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Single entry point for writing audit_logs rows.
 *
 * Usage:
 *   AuditService::log(user(), AuditAction::Login, 'users', $user->id, old, new);
 *   AuditService::log(null, AuditAction::LoginFailed, 'users');  // unknown actor
 *
 * Observers use logModelChange() so every create/update/delete on an audited
 * model is recorded with the same shape automatically.
 */
class AuditService
{
    /** Actions that mean "credentials changed" — logged without secret values. */
    private const SENSITIVE_KEYS = ['password', 'remember_token', 'two_factor_code'];

    /** System-user placeholder for rows without an authenticated actor. */
    public const SYSTEM_USER_ID = null;

    /**
     * Write one audit_logs entry.
     */
    public static function log(
        ?User $user,
        AuditAction|string $action,
        string $tableName,
        int|string|null $recordId = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        if ($action instanceof AuditAction) {
            $action = $action->value;
        }

        return AuditLog::create([
            'user_id'    => $user?->id,
            'action'     => $action,
            'table_name' => $tableName,
            'record_id'  => $recordId !== null && $recordId !== '' ? (int) $recordId : null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Record an Eloquent model change. Used by model observers.
     *
     * - created → new_values = current attributes
     * - updated → old/new limited to the changed columns
     * - deleted → old_values = last-known attributes
     */
    public static function logModelChange(
        Model $model,
        AuditAction $action,
        array $hideKeys = [],
        array $ignoreKeys = ['updated_at'],
        ?array $trackedFields = null,
    ): ?AuditLog {
        $hidden = array_merge(self::SENSITIVE_KEYS, $hideKeys);

        $attributes = collect($model->getAttributes())
            ->except(array_merge($hidden, $ignoreKeys));

        $old = [];
        $new = [];

        if ($action === AuditAction::Created) {
            $new = $attributes->all();
        } elseif ($action === AuditAction::Deleted) {
            $old = $attributes->all();
        } else {
            // Updated: only the changed columns.
            foreach ($model->getDirty() as $key => $value) {
                if (in_array($key, $ignoreKeys, true)) {
                    continue;
                }
                if (in_array($key, $hidden, true)) {
                    $old[$key] = 'changed';
                    $new[$key] = 'changed';
                    continue;
                }
                if ($trackedFields !== null && ! in_array($key, $trackedFields, true)) {
                    continue;
                }
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $value;
            }

            if ($new === []) {
                return null; // nothing auditable changed
            }
        }

        return self::log(
            self::resolveUser(),
            $action,
            $model->getTable(),
            $model->getKey(),
            $old,
            $new,
        );
    }

    /** The acting user for the current request (null on console/unknown). */
    public static function resolveUser(): ?User
    {
        return Request::user() ?? auth()->user();
    }
}
