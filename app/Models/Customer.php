<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_number',
        'full_name',
        'email',
        'phone',
        'address',
        'city',
        'customer_type',
        'kyc_status',
        'risk_level',
        'registration_date',
        'branch_id',
        'relationship_manager_id',
    ];

    protected $casts = [
        'registration_date' => 'date',
    ];

    /**
     * Get the branch that serves the customer.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the relationship manager (bank employee) assigned to the customer.
     */
    public function relationshipManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'relationship_manager_id');
    }

    /**
     * Get all bank accounts belonging to this customer.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get all loans belonging to this customer.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
