<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    /**
     * List alerts with optional filters.
     * Filters: severity, status, assigned_to, alert_type
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $alerts = Alert::query()
            ->with(['assignedTo', 'alertable'])
            ->when($request->severity,    fn ($q) => $q->where('severity', $request->severity))
            ->when($request->status,      fn ($q) => $q->where('status', $request->status))
            ->when($request->assigned_to, fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->alert_type,  fn ($q) => $q->where('alert_type', $request->alert_type))
            ->latest()
            ->paginate(15);

        return AlertResource::collection($alerts);
    }

    /**
     * Show a single alert with assignee and the triggering entity.
     */
    public function show(string $id): AlertResource
    {
        $alert = Alert::with(['assignedTo', 'alertable'])
            ->findOrFail($id);

        return AlertResource::make($alert);
    }

    /**
     * Resolve an open alert, recording the resolution timestamp.
     */
    public function resolve(string $id): JsonResponse
    {
        $alert = Alert::where('status', 'open')
            ->findOrFail($id);

        $alert->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully.',
            'data'    => AlertResource::make($alert->fresh(['assignedTo', 'alertable'])),
        ]);
    }

    /**
     * Assign an alert to a specific bank employee for investigation.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $alert = Alert::findOrFail($id);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $alert->update(['assigned_to' => $validated['user_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Alert assigned successfully.',
            'data'    => AlertResource::make($alert->fresh(['assignedTo', 'alertable'])),
        ]);
    }
}
