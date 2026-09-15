<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    public const NOTIFICATION_DEFAULTS = [
        'email_notifications'   => true,
        'push_notifications'    => true,
        'alert_preferences'     => [
            'critical' => true,
            'high'     => true,
            'medium'   => true,
            'low'      => false,
        ],
        'transaction_alerts'    => true,
        'loan_alerts'           => true,
        'account_alerts'        => true,
        'weekly_digest'         => false,
    ];

    public const PREFERENCE_DEFAULTS = [
        'theme'          => 'dark',     // dark, light, system
        'language'       => 'en',       // en, fr, es, de, ar
        'dashboard_view' => 'default',  // default, compact, detailed
        'timezone'       => 'UTC',
        'date_format'    => 'Y-m-d',
        'items_per_page' => 15,
    ];

    protected $fillable = [
        'user_id',
        'two_factor_enabled',
        'two_factor_channel',
        'notification_settings',
        'preferences',
        'two_factor_code_attempts',
        'two_factor_last_sent_at',
    ];

    protected $casts = [
        'two_factor_enabled'    => 'boolean',
        'notification_settings' => 'array',
        'preferences'           => 'array',
        'two_factor_code_attempts' => 'integer',
        'two_factor_last_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Notification settings merged over the sanctioned defaults.
     */
    public function notifications(): array
    {
        return array_replace_recursive(
            self::NOTIFICATION_DEFAULTS,
            $this->notification_settings ?? []
        );
    }

    /**
     * UI preferences merged over the sanctioned defaults.
     */
    public function preferences(): array
    {
        return array_replace_recursive(
            self::PREFERENCE_DEFAULTS,
            $this->preferences ?? []
        );
    }
}
