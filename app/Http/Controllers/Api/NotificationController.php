<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\IndexNotificationsRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Latest notifications for the authenticated user + unread count.
     */
    public function index(IndexNotificationsRequest $request): JsonResponse
    {
        $limit = $request->validated('limit') ?? 15;

        return response()->json([
            'success' => true,
            'data'    => $this->notificationService->listNotifications($request->user(), (int) $limit),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $updated = $this->notificationService->markRead($request->user(), $id);

        return response()->json([
            'success' => true,
            'data'    => ['updated' => $updated],
        ]);
    }

    /**
     * Mark every notification as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->notificationService->markAllRead($request->user());

        return response()->json([
            'success' => true,
            'data'    => ['updated' => $updated],
        ]);
    }
}
