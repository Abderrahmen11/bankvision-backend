<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_code',
        'branch_name',
        'address',
        'city',
        'phone',
        'manager_id',
        'total_employees',
        'status',
    ];

    /**
     * Get the manager of this branch.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the employees (users) at this branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    /**
     * Get the customers served by this branch.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'branch_id');
    }
}
