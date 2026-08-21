<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Return high-level KPI stats for the dashboard.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getStats(),
        ]);
    }

    /**
     * Return daily transaction volume for the last 30 days.
     */
    public function chartData(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getChartData(30),
        ]);
    }

    /**
     * Return recent transactions and open alerts for activity feed.
     */
    public function recentActivity(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getRecentActivity(10),
        ]);
    }
}
