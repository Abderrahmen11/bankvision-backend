<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardLayout\UpdateDashboardLayoutRequest;
use App\Http\Resources\DashboardLayoutResource;
use App\Services\DashboardLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardLayoutController extends Controller
{
    public function __construct(
        protected DashboardLayoutService $layoutService
    ) {}

    /**
     * Get the authenticated user's dashboard layout, or fallback to role default.
     */
    public function index(Request $request): JsonResponse
    {
        $layout = $this->layoutService->getOrCreateLayout($request->user());

        return response()->json([
            'success' => true,
            'data'    => DashboardLayoutResource::make($layout),
        ]);
    }

    /**
     * Update and persist the authenticated user's dashboard layout.
     */
    public function update(UpdateDashboardLayoutRequest $request): JsonResponse
    {
        $layout = $this->layoutService->saveLayout(
            $request->user(),
            $request->validated('layout_data')
        );

        return response()->json([
            'success' => true,
            'message' => 'Dashboard layout updated successfully.',
            'data'    => DashboardLayoutResource::make($layout),
        ]);
    }

    /**
     * Reset the authenticated user's dashboard layout to their role-based default.
     */
    public function reset(Request $request): JsonResponse
    {
        $layout = $this->layoutService->resetLayout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard layout reset to default successfully.',
            'data'    => DashboardLayoutResource::make($layout),
        ]);
    }
}
