<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLayout extends Model
{
    /** @use HasFactory<\Database\Factories\DashboardLayoutFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'layout_data',
        'is_default',
    ];

    protected $casts = [
        'layout_data' => 'array',
        'is_default'  => 'boolean',
    ];

    /**
     * Get the user who owns this dashboard layout preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
