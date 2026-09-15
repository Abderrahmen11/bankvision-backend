<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SarFiling extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'under_review', 'filed', 'escalated'];

    public const CATEGORIES = [
        'Structuring / Smurfing (<$10k Cash)',
        'Rapid Wire Movement / Pass-through Account',
        'Unusual Transaction for Profile / Industry',
        'Suspected Shell Company / Opaque Ownership',
        'PEP (Politically Exposed Person) Sanctions Check',
        'Terrorist Financing Suspicion',
        'Cyber Fraud / Account Takeover Infiltration',
    ];

    protected $fillable = [
        'reference',
        'customer_name',
        'customer_number',
        'category',
        'amount',
        'status',
        'narrative',
        'action_taken',
        'user_id',
        'alert_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $filing) {
            if (empty($filing->reference)) {
                $filing->reference = 'SAR-' . date('Y') . '-' . Str::upper((string) Str::ulid());
            }
        });
    }

    /**
     * The compliance officer who filed this report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The alert that escalated into this filing, if any.
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /**
     * Scope to a filing status.
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn ($q) => $q->where('status', $status));
    }

    /**
     * Scope to a free-text search across reference, customer and category.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) =>
            $q->where(fn ($q) =>
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_number', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
            )
        );
    }
}
