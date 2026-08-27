<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Return high-level KPI stats for the dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getStats($request->user()),
        ]);
    }

    /**
     * Return daily transaction volume for the last 30 days.
     */
    public function chartData(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getChartData($days, $request->user()),
        ]);
    }

    /**
     * Return recent transactions and open alerts for activity feed.
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);

        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getRecentActivity($limit, $request->user()),
        ]);
    }

    /**
     * Return comprehensive risk analysis across customers, loans, transactions, and branches.
     */
    public function riskAnalysis(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getRiskAnalysis($request->user()),
        ]);
    }

    /**
     * Return analytical portfolio and transaction reports.
     */
    public function reports(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getReports($request->user()),
        ]);
    }

    /**
     * Return Auditor investigation dashboard: audit log counts, suspicious indicators,
     * high-risk summaries, and recent activity timeline.
     */
    public function auditStats(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getAuditStats($request->user()),
        ]);
    }

    /**
     * Return Auditor investigation report: user activity summary, destructive actions,
     * high-risk customers, flagged transactions, and at-risk loans.
     */
    public function auditReport(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboardService->getAuditReport($request->user()),
        ]);
    }
}
