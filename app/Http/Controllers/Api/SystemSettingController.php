<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class SystemSettingController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService
    ) {}

    /**
     * Institution-wide configuration (bank profile, currency, interest rates).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->settingsService->getSystemSettings(),
        ]);
    }

    /**
     * Update institution-wide configuration.
     */
    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsService->updateSystemSettings($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'System configuration updated successfully.',
            'data'    => $settings,
        ]);
    }

    /**
     * Platform health snapshot for system monitoring.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->settingsService->healthReport(),
        ]);
    }

    /**
     * Public product interest rates (published rates, no authentication —
     * mirrors what a bank discloses on its website).
     */
    public function publicInterestRates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->settingsService->getSystemSettings()['interest'],
        ]);
    }
}
