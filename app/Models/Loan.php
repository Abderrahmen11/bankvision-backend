<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope query to active loans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to delinquent loans.
     */
    public function scopeDelinquent(Builder $query): Builder
    {
        return $query->where('status', 'delinquent');
    }

    /**
     * Scope query to apply multiple attribute filters.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) =>
            $q->where(fn ($q) =>
                $q->where('loan_number', 'like', "%{$search}%")
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
            ->when($filters['loan_type'] ?? $filters['type'] ?? null, fn ($q, $t) => $q->where('loan_type', $t))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['term_months'] ?? null, fn ($q, $m) => $q->where('term_months', $m))
            ->when(isset($filters['principal_amount_min']), fn ($q) => $q->where('principal_amount', '>=', $filters['principal_amount_min']))
            ->when(isset($filters['principal_amount_max']), fn ($q) => $q->where('principal_amount', '<=', $filters['principal_amount_max']))
            ->when(isset($filters['outstanding_balance_min']), fn ($q) => $q->where('outstanding_balance', '>=', $filters['outstanding_balance_min']))
            ->when(isset($filters['outstanding_balance_max']), fn ($q) => $q->where('outstanding_balance', '<=', $filters['outstanding_balance_max']))
            ->when(isset($filters['interest_rate_min']), fn ($q) => $q->where('interest_rate', '>=', $filters['interest_rate_min']))
            ->when(isset($filters['interest_rate_max']), fn ($q) => $q->where('interest_rate', '<=', $filters['interest_rate_max']));
    }
}
