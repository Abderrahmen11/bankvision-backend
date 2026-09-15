<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationSettingsRequest;
use App\Http\Requests\Settings\UpdatePreferencesRequest;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService
    ) {}

    /**
     * All settings for the authenticated user (2FA, notifications, preferences).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->settingsService->getUserSettings($request->user()),
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $notifications = $this->settingsService->updateNotifications(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully.',
            'data'    => $notifications,
        ]);
    }

    /**
     * Update UI preferences (theme, language, dashboard view, timezone).
     */
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $preferences = $this->settingsService->updatePreferences(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully.',
            'data'    => $preferences,
        ]);
    }
}
