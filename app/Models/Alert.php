<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    /** @use HasFactory<\Database\Factories\AlertFactory> */
    use HasFactory;

    /**
     * Auto-generate a unique alert number before inserting.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $alert) {
            if (empty($alert->alert_number)) {
                $alert->alert_number = 'ALT-' . date('Y') . '-' . rand(10000, 99999);
            }
        });
    }

    protected $fillable = [
        'alert_number',
        'alert_type',
        'severity',
        'description',
        'resolved_at',
        'status',
        'assigned_to',
        'alertable_type',
        'alertable_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * The bank employee assigned to investigate this alert.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Polymorphic: the entity that triggered this alert.
     * Can be a Customer, Account, Transaction, or Loan.
     */
    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope query to open alerts.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope query to specific severity level.
     */
    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope query to search alerts by alert number or description.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) =>
            $q->where(fn ($q) =>
                $q->where('alert_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
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
            ->when($filters['severity'] ?? null,    fn ($q, $s) => $q->where('severity', $s))
            ->when($filters['status'] ?? null,      fn ($q, $s) => $q->where('status', $s))
            ->when($filters['assigned_to'] ?? null, fn ($q, $a) => $q->where('assigned_to', $a))
            ->when($filters['alert_type'] ?? null,  fn ($q, $t) => $q->where('alert_type', $t));
    }
}

