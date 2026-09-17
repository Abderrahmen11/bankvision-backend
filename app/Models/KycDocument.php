<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    use HasFactory;

    public const DOCUMENT_TYPES = [
        'passport',
        'national_id',
        'drivers_license',
        'utility_bill',
        'tax_certificate',
        'corporate_registry',
    ];

    public const STATUSES = [
        'pending',
        'verified',
        'rejected',
    ];

    protected $fillable = [
        'customer_id',
        'document_type',
        'document_number',
        'issuing_country',
        'expiry_date',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'uploaded_at',
        'verified_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'file_size'   => 'integer',
    ];

    /**
     * The customer this KYC document belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The staff user who uploaded this document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to customer.
     */
    public function scopeForCustomer(Builder $query, int|string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to status.
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn ($q) => $q->where('status', $status));
    }
}
