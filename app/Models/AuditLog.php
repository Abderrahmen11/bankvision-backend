<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the bank employee who performed the audited action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'System / Deleted User',
            'email' => 'system@bankvision.com',
            'role' => 'system',
        ]);
    }

    /**
     * Scope query to search audit logs by action, table name, IP, or user details.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) =>
            $q->where(fn ($q) =>
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('table_name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($uq) =>
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                  )
            )
        );
    }

    /**
     * Scope query to apply multiple attribute filters.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['search'] ?? null)
            ->when($filters['action'] ?? null,     fn ($q, $a) => $q->where('action', $a))
            ->when($filters['table_name'] ?? null, fn ($q, $t) => $q->where('table_name', $t))
            ->when($filters['user_id'] ?? null,    fn ($q, $u) => $q->where('user_id', $u))
            ->when($filters['record_id'] ?? null,  fn ($q, $r) => $q->where('record_id', $r))
            ->when($filters['ip_address'] ?? null, fn ($q, $ip) => $q->where('ip_address', 'like', "%{$ip}%"))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->whereHas('user', fn ($uq) => $uq->where('role', $role)))
            ->when($filters['date_from'] ?? null,  fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null,    fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }
}

