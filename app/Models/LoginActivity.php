<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'successful',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device',
        'logged_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'logged_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an authentication event, parsing basic device metadata from the user agent.
     */
    public static function record(User $user, string $event, bool $successful = true): self
    {
        $agent = request()->userAgent() ?? '';
        $ip    = request()->ip();

        return static::create([
            'user_id'    => $user->id,
            'event'      => $event,
            'successful' => $successful,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'browser'    => static::parseBrowser($agent),
            'platform'   => static::parsePlatform($agent),
            'device'     => static::parseDevice($agent),
            'logged_at'  => now(),
        ]);
    }

    public static function parseBrowser(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Edg/')     => 'Microsoft Edge',
            str_contains($agent, 'OPR/')     => 'Opera',
            str_contains($agent, 'Chrome/')  => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/')  => 'Safari',
            $agent === ''                    => 'Unknown',
            default                          => 'Other',
        };
    }

    public static function parsePlatform(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Windows')  => 'Windows',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Android')  => 'Android',
            str_contains($agent, 'iPhone')   => 'iOS',
            str_contains($agent, 'Linux')    => 'Linux',
            $agent === ''                    => 'Unknown',
            default                          => 'Other',
        };
    }

    public static function parseDevice(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Mobile')  => 'Mobile',
            str_contains($agent, 'Tablet')  => 'Tablet',
            $agent === ''                   => 'Unknown',
            default                         => 'Desktop',
        };
    }
}
