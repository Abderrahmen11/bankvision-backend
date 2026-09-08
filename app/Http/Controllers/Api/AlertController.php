<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alert\AssignAlertRequest;
use App\Http\Requests\Alert\IndexAlertRequest;
use App\Http\Resources\AlertResource;
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
     * List alerts with search, filters, sorting, and role-based scoping.
     */
    public function index(IndexAlertRequest $request): AnonymousResourceCollection
    {
        $alerts = $this->alertService->getPaginatedAlerts(
            $request->validated(),
            user: $request->user()
        );

        return AlertResource::collection($alerts);
    }

    /**
     * Show a single alert.
     */
    public function show(Request $request, string $id): AlertResource
    {
        $alert = $this->alertService->getAlertDetails($id, $request->user());

        return AlertResource::make($alert);
    }

    /**
     * Resolve an open alert.
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $resolved = $this->alertService->resolveAlert($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully.',
            'data'    => AlertResource::make($resolved),
        ]);
    }

    /**
     * Assign an alert to a specific bank employee.
     */
    public function assign(AssignAlertRequest $request, string $id): JsonResponse
    {
        $assigned = $this->alertService->assignAlert($id, (int) $request->validated('user_id'), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Alert assigned successfully.',
            'data'    => AlertResource::make($assigned),
        ]);
    }
}
