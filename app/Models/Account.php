<?php

namespace App\Models;

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
     * Scope query to active accounts.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to apply filters.
     */
    public function scopeFilter(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->when($filters['customer_id'] ?? null, fn ($q, $c) => $q->where('customer_id', $c))
            ->when($filters['type'] ?? null,        fn ($q, $t) => $q->where('account_type', $t))
            ->when($filters['status'] ?? null,      fn ($q, $s) => $q->where('status', $s))
            ->when($filters['currency'] ?? null,    fn ($q, $c) => $q->where('currency', $c));
    }
}
