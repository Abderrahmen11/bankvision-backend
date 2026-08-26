<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'account_number',
        'customer_id',
        'account_type',
        'currency',
        'balance',
        'status',
        'opened_date',
        'interest_rate',
    ];

    protected $casts = [
        'balance'       => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'opened_date'   => 'date',
    ];

    /**
     * The customer who owns this account.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * All transactions made on this account.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all security and compliance alerts for this account.
     */
    public function alerts(): MorphMany
    {
        return $this->morphMany(Alert::class, 'alertable');
    }

    /**
     * Check if account is active and permitted to process transactions.
     */
    public function canTransact(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account balance is sufficient for debit without going negative.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return ((float) $this->balance) >= $amount;
    }

    /**
     * Scope query to search by account number, customer name, or customer number.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) =>
            $q->where(fn ($q) =>
                $q->where('account_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($cq) =>
                      $cq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('customer_number', 'like', "%{$search}%")
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
            ->when($filters['customer_id'] ?? null, fn ($q, $c) => $q->where('customer_id', $c))
            ->when($filters['type'] ?? null,        fn ($q, $t) => $q->where('account_type', $t))
            ->when($filters['status'] ?? null,      fn ($q, $s) => $q->where('status', $s))
            ->when($filters['currency'] ?? null,    fn ($q, $c) => $q->where('currency', $c))
            ->when($filters['branch_id'] ?? null,   fn ($q, $b) =>
                $q->whereHas('customer', fn ($cq) => $cq->where('branch_id', $b))
            )
            ->when(isset($filters['balance_min']), fn ($q) => $q->where('balance', '>=', $filters['balance_min']))
            ->when(isset($filters['balance_max']), fn ($q) => $q->where('balance', '<=', $filters['balance_max']));
    }
}
