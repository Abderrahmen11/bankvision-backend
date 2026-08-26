<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'account_id',
        'transaction_type',
        'amount',
        'currency',
        'transaction_date',
        'description',
        'status',
        'channel',
        'counterparty',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'datetime',
        'approved_at'      => 'datetime',
    ];

    /**
     * The account this transaction belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The bank employee who approved this transaction.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all security and compliance alerts triggered by this transaction.
     */
    public function alerts(): MorphMany
    {
        return $this->morphMany(Alert::class, 'alertable');
    }

    /**
     * Scope query to completed transactions.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope query to flagged transactions.
     */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('status', 'flagged');
    }

    /**
     * Scope query to search by transaction number or counterparty.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) =>
            $q->where(fn ($q) =>
                $q->where('transaction_number', 'like', "%{$search}%")
                  ->orWhere('counterparty', 'like', "%{$search}%")
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
            ->when($filters['account_id'] ?? null,  fn ($q, $a) => $q->where('account_id', $a))
            ->when($filters['transaction_type'] ?? $filters['type'] ?? null, fn ($q, $t) => $q->where('transaction_type', $t))
            ->when($filters['status'] ?? null,      fn ($q, $s) => $q->where('status', $s))
            ->when($filters['channel'] ?? null,     fn ($q, $c) => $q->where('channel', $c))
            ->when($filters['approved_by'] ?? null, fn ($q, $u) => $q->where('approved_by', $u))
            ->when($filters['date_from'] ?? null,   fn ($q, $d) => $q->whereDate('transaction_date', '>=', $d))
            ->when($filters['date_to'] ?? null,     fn ($q, $d) => $q->whereDate('transaction_date', '<=', $d));
    }
}
