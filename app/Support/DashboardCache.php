<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Cache invalidation for the dashboard KPI endpoints.
 *
 * The dashboard cache keys are scope-aware (branch-scoped roles see their
 * branch's numbers, everyone else sees bank-wide totals), so flushing is done
 * by enumerating the known scopes instead of using cache tags — the file
 * driver does not support tags.
 *
 * Key formats (keep in sync with DashboardService):
 *   dashboard:stats:global | dashboard:stats:branch:{id}:{role}
 *   dashboard:chart_data:{days}:global | dashboard:chart_data:{days}:branch:{id}
 *   dashboard:recent_activity:global | dashboard:recent_activity:branch:{id}:user:{uid}
 */
class DashboardCache
{
    /** Day windows the frontend requests (chart range pills). */
    public const CHART_DAYS = [7, 14, 30, 90];

    public static function flushStats(): void
    {
        Cache::forget('dashboard:stats:global');

        foreach (self::branchScopes() as $scope) {
            Cache::forget("dashboard:stats:{$scope}");
        }
    }

    public static function flushChartData(): void
    {
        foreach (self::CHART_DAYS as $days) {
            Cache::forget("dashboard:chart_data:{$days}:global");

            foreach (self::branchScopes() as $scope) {
                Cache::forget("dashboard:chart_data:{$days}:{$scope}");
            }
        }
    }

    public static function flushRecentActivity(): void
    {
        Cache::forget('dashboard:recent_activity:global');

        // Branch-scoped results are user-specific (assigned alerts are part of
        // the feed), so every scoped user owns a key.
        User::query()
            ->whereIn('role', Role::branchScoped())
            ->whereNotNull('branch_id')
            ->select('id', 'branch_id')
            ->get()
            ->each(fn (User $u) => Cache::forget(
                "dashboard:recent_activity:branch:{$u->branch_id}:user:{$u->id}"
            ));
    }

    public static function flushReports(): void
    {
        // Bump the generation — every dashboard:reports:gen{N}:* key becomes
        // unreachable at once, regardless of which filter combination it was
        // cached under.
        Cache::forever('dashboard:reports:generation', Cache::get('dashboard:reports:generation', 1) + 1);
    }

    public static function flushAll(): void
    {
        self::flushStats();
        self::flushChartData();
        self::flushRecentActivity();
        self::flushReports();
    }

    /** @return string[] branch scope fragments, e.g. "branch:3" */
    private static function branchScopes(): array
    {
        return Branch::query()->pluck('id')
            ->map(fn ($id) => "branch:{$id}")
            ->all();
    }
}
