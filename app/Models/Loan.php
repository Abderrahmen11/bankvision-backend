<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Loan extends Model
{
    /** @use HasFactory<\Database\Factories\LoanFactory> */
    use HasFactory;

    protected $fillable = [
        'loan_number',
        'customer_id',
        'loan_type',
        'principal_amount',
        'outstanding_balance',
        'interest_rate',
        'term_months',
        'start_date',
        'end_date',
        'status',
        'next_payment_date',
    ];

    protected $casts = [
        'principal_amount'    => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'interest_rate'       => 'decimal:2',
        'start_date'          => 'date',
        'end_date'            => 'date',
        'next_payment_date'   => 'date',
    ];

    /**
     * The customer who holds this loan.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all security and compliance alerts associated with this loan.
     */
    public function alerts(): MorphMany
    {
        return $this->morphMany(Alert::class, 'alertable');
    }

    /**
     * Scope query to pending loans.
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope query to active loans.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to delinquent loans.
     */
    public function scopeDelinquent(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'delinquent');
    }

    /**
     * Scope query to apply multiple attribute filters.
     */
    public function scopeFilter(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->when($filters['customer_id'] ?? null, fn ($q, $c) => $q->where('customer_id', $c))
            ->when($filters['type'] ?? null,        fn ($q, $t) => $q->where('loan_type', $t))
            ->when($filters['status'] ?? null,      fn ($q, $s) => $q->where('status', $s));
    }
}
