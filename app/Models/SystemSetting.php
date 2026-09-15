<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public const BANK_DEFAULTS = [
        'name'          => 'BankVision National Bank',
        'legal_name'    => 'BankVision Financial Group Ltd.',
        'address'       => 'One Financial Plaza, 100 Meridian Avenue',
        'city'          => 'New York',
        'country'       => 'United States',
        'phone'         => '+1 (212) 555-0100',
        'email'         => 'contact@bankvision.com',
        'swift_code'    => 'BVNKUS33XXX',
        'website'       => 'https://www.bankvision.com',
    ];

    public const CURRENCY_DEFAULTS = [
        'code'   => 'USD',
        'symbol' => '$',
    ];

    public const INTEREST_DEFAULTS = [
        'savings_rate'         => 2.50,
        'checking_rate'        => 0.50,
        'fixed_deposit_rate'   => 3.25,
        'personal_loan_rate'   => 8.50,
        'business_loan_rate'   => 6.50,
        'mortgage_rate'        => 4.50,
        'overdraft_rate'       => 12.00,
        'late_payment_penalty' => 1.50,
    ];

    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Read a settings payload by key, merged over its group defaults.
     */
    public static function payload(string $key, array $defaults): array
    {
        return array_replace_recursive(
            $defaults,
            static::query()->where('key', $key)->value('value') ?? []
        );
    }

    /**
     * Persist a settings payload under a key.
     */
    public static function put(string $key, string $group, array $payload): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'value' => $payload]
        );
    }

    /**
     * Scope to a settings group.
     */
    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }
}
