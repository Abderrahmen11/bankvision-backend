<?php

namespace App\Services;

use App\Models\Alert;
use App\Enums\AuditAction;
use App\Services\AuditService;use App\Models\Loan;
use App\Models\LoginActivity;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsService
{
    /**
     * Resolve (creating if needed) the settings row for a user.
     */
    public function forUser(User $user): UserSetting
    {
        $settings = $user->settingsOrCreate();

        // Hydrate database defaults into memory for freshly created rows
        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
        }

        return $settings;
    }

    /**
     * Full settings payload for the authenticated user.
     */
    public function getUserSettings(User $user): array
    {
        $settings = $this->forUser($user);

        return [
            'two_factor_enabled' => $settings->two_factor_enabled,
            'two_factor_channel' => $settings->two_factor_channel,
            'notifications'      => $settings->notifications(),
            'preferences'        => $settings->preferences(),
        ];
    }

    /**
     * Merge and persist notification settings.
     */
    public function updateNotifications(User $user, array $payload): array
    {
        $before = $this->getUserSettings($user)['notifications'];

        $result = $this->applyUpdateNotifications($user, $payload);

        AuditService::log($user, AuditAction::NotificationsUpdated, 'users', $user->id, $before, $result);

        return $result;
    }

    private function applyUpdateNotifications(User $user, array $payload): array
    {
        $settings = $this->forUser($user);

        $settings->update([
            'notification_settings' => array_replace_recursive(
                $settings->notifications(),
                $payload
            ),
        ]);

        return $settings->notifications();
    }

    /**
     * Merge and persist UI preferences.
     */
    public function updatePreferences(User $user, array $payload): array
    {
        $before = $this->getUserSettings($user)['preferences'];

        $result = $this->applyUpdatePreferences($user, $payload);

        AuditService::log($user, AuditAction::PreferencesUpdated, 'users', $user->id, $before, $result);

        return $result;
    }

    private function applyUpdatePreferences(User $user, array $payload): array
    {
        $settings = $this->forUser($user);

        $settings->update([
            'preferences' => array_replace_recursive(
                $settings->preferences(),
                $payload
            ),
        ]);

        return $settings->preferences();
    }

    /**
     * Toggle two-factor authentication with an optional delivery channel.
     */
    public function setTwoFactor(User $user, bool $enabled, ?string $channel = null): array
    {
        $settings = $this->forUser($user);

        $settings->update([
            'two_factor_enabled' => $enabled,
            'two_factor_channel' => $enabled ? ($channel ?? $settings->two_factor_channel ?? 'email') : null,
        ]);

        return [
            'two_factor_enabled' => $settings->two_factor_enabled,
            'two_factor_channel' => $settings->two_factor_channel,
        ];
    }

    /**
     * Institution-wide configuration merged over defaults.
     */
    public function getSystemSettings(): array
    {
        return [
            'bank'     => SystemSetting::payload('bank.profile', SystemSetting::BANK_DEFAULTS),
            'currency' => SystemSetting::payload('currency.defaults', SystemSetting::CURRENCY_DEFAULTS),
            'interest' => SystemSetting::payload('interest.rates', SystemSetting::INTEREST_DEFAULTS),
        ];
    }

    /**
     * Persist institution-wide configuration groups.
     */
    public function updateSystemSettings(array $payload, ?User $user = null): array
    {
        $before    = $this->getSystemSettings();
        $currencyBefore = $before['bank']['profile']['currency'] ?? ($before['bank']['profile']['base_currency'] ?? null);

        $map = [
            'bank'     => ['key' => 'bank.profile', 'group' => 'bank'],
            'currency' => ['key' => 'currency.defaults', 'group' => 'currency'],
            'interest' => ['key' => 'interest.rates', 'group' => 'interest'],
        ];

        foreach ($map as $group => $meta) {
            if (isset($payload[$group]) && is_array($payload[$group])) {
                $current = SystemSetting::payload($meta['key'], match ($group) {
                    'bank'     => SystemSetting::BANK_DEFAULTS,
                    'currency' => SystemSetting::CURRENCY_DEFAULTS,
                    default    => SystemSetting::INTEREST_DEFAULTS,
                });

                SystemSetting::put($meta['key'], $meta['group'], array_replace_recursive($current, $payload[$group]));
            }
        }

        $after = $this->getSystemSettings();
        $currencyAfter = $after['bank']['profile']['currency'] ?? ($after['bank']['profile']['base_currency'] ?? null);

        AuditService::log($user, AuditAction::SystemSettingsUpdated, 'system_settings', null, $before, $after);

        if ($currencyBefore !== null && $currencyAfter !== null && $currencyBefore !== $currencyAfter) {
            AuditService::log($user, AuditAction::CurrencyUpdated, 'system_settings', null, [
                'currency' => $currencyBefore,
            ], ['currency' => $currencyAfter]);
        }

        return $after;
    }

    /**
     * Platform health snapshot for the system monitoring panel.
     */
    public function healthReport(): array
    {
        // Health probes include a live DB round-trip; a short cache keeps the
        // dashboard's system-health widget from hammering the database.
        return Cache::remember('system:health:report', 10, function (): array {
            return $this->buildHealthReport();
        });
    }

    private function buildHealthReport(): array
    {
        $dbStart = microtime(true);
        $dbOk    = true;

        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }
        $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);

        $cacheOk = true;
        try {
            Cache::store('file')->put('bankvision:health-ping', true, 5);
            $cacheOk = Cache::store('file')->get('bankvision:health-ping') === true;
        } catch (\Throwable) {
            $cacheOk = false;
        }

        $diskTotal = (float) @disk_total_space('/') ?: 0;
        $diskFree  = (float) @disk_free_space('/') ?: 0;

        return [
            'database' => [
                'status'      => $dbOk ? 'healthy' : 'down',
                'latency_ms'  => $dbLatency,
                'connections' => 1,
            ],
            'cache' => [
                'status' => $cacheOk ? 'healthy' : 'degraded',
                'driver' => config('cache.default'),
            ],
            'storage' => [
                'status'          => $diskFree > 0 ? 'healthy' : 'degraded',
                'disk_used_pct'   => $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0,
                'disk_free_bytes' => (int) $diskFree,
            ],
            'application' => [
                'status'     => 'healthy',
                'environment'=> app()->environment(),
                'php_version'=> PHP_VERSION,
                'laravel'    => app()->version(),
                'timezone'   => config('app.timezone'),
            ],
            'activity' => [
                'total_users'          => User::count(),
                'active_sessions'      => PersonalAccessToken::query()->count(),
                'failed_logins_24h'    => LoginActivity::where('event', 'failed_login')->where('logged_at', '>=', now()->subDay())->count(),
                'open_alerts'          => Alert::whereIn('status', ['open', 'in-progress'])->count(),
                'pending_transactions' => Transaction::where('status', 'pending')->count(),
                'pending_loans'        => Loan::where('status', 'pending')->count(),
            ],
            'checked_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
