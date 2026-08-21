<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    public function __construct(
        protected AlertService $alertService
    ) {}

    /**
     * List alerts with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $alerts = $this->alertService->getPaginatedAlerts(
            $request->only(['severity', 'status', 'assigned_to', 'alert_type'])
        );

        return AlertResource::collection($alerts);
    }

    /**
     * Show a single alert.
     */
    public function show(string $id): AlertResource
    {
        $alert = $this->alertService->getAlertDetails($id);

        return AlertResource::make($alert);
    }

    /**
     * Resolve an open alert.
     */
    public function resolve(string $id): JsonResponse
    {
        $alert = Alert::where('status', 'open')->findOrFail($id);
        $resolved = $this->alertService->resolveAlert($alert);

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully.',
            'data'    => AlertResource::make($resolved),
        ]);
    }

    /**
     * Assign an alert to a specific bank employee.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $alert = Alert::findOrFail($id);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $assigned = $this->alertService->assignAlert($alert, (int) $validated['user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Alert assigned successfully.',
            'data'    => AlertResource::make($assigned),
        ]);
    }
}
