<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    /** @use HasFactory<\Database\Factories\AlertFactory> */
    use HasFactory;

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
}
