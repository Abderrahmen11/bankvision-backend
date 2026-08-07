<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
