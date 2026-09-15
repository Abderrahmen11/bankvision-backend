<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\AuditAction;
use App\Services\AuditService;use App\Http\Requests\Settings\StoreApiTokenRequest;
use App\Models\LoginActivity;
use App\Services\SettingsService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService,
        protected TwoFactorService $twoFactor
    ) {}

    /**
     * Disable two-factor authentication (takes effect immediately).
     * Enabling requires the emailed code — use send-code + verify.
     */
    public function updateTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
            'channel' => ['sometimes', 'string', 'in:email,sms,authenticator'],
        ]);

        $enabled = $request->boolean('enabled');
        // Email defaults in; email/sms require code verification before the
        // flag sticks. Authenticator apps enable directly (no email proof).
        $channel = $request->input('channel', 'email');

        if ($enabled && $channel !== 'email') {
            return response()->json([
                'success' => false,
                'message' => 'Only the email channel is currently supported for two-factor authentication.',
            ], 422);
        }

        if ($enabled && $channel === 'email') {
            $this->sendTwoFactorCode($request);

            return response()->json([
                'success' => false,
                'requires_verification' => true,
                'message' => 'A verification code has been sent to your email. Confirm the code to enable two-factor authentication.',
            ], 422);
        }

        if ($enabled) {
            $this->twoFactor->setEnabled($request->user(), true, $channel);
            $result = $this->settingsService->setTwoFactor($request->user(), true, $channel);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication enabled.',
                'data'    => $result,
            ]);
        }

        $this->twoFactor->setEnabled($request->user(), false);
        $result = $this->settingsService->setTwoFactor($request->user(), false, null);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled.',
            'data'    => $result,
        ]);
    }

    /**
     * Email a one-time verification code used to enable 2FA.
     */
    public function sendTwoFactorCode(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => ['sometimes', 'string', 'in:email,sms,authenticator'],
        ]);

        $channel = $request->input('channel', 'email');

        if ($channel !== 'email') {
            return response()->json([
                'success' => false,
                'message' => 'Only the email channel is currently supported for verification codes.',
            ], 422);
        }

        $this->twoFactor->assertResendAllowed($request->user());
        $this->twoFactor->issueCode($request->user(), 'enable');

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email address. It expires in 10 minutes.',
        ]);
    }

    /**
     * Verify the emailed code and activate two-factor authentication.
     */
    public function verifyTwoFactorCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$this->twoFactor->verifyCode($user, $request->input('code'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        AuditService::log($user, AuditAction::TwoFactorVerified, 'users', $user->id);

        $this->twoFactor->setEnabled($user, true, 'email');

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled. You will be asked for a code at every sign-in.',
            'data'    => [
                'two_factor_enabled'  => true,
                'two_factor_channel'  => 'email',
            ],
        ]);
    }

    /**
     * List the authenticated user's active sessions (Sanctum tokens).
     */
    public function sessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        $sessions = $request->user()
            ->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id'            => $token->id,
                'name'          => $token->name,
                'is_current'    => $token->id === $currentId,
                'last_used_at'  => $token->last_used_at?->format('Y-m-d H:i:s'),
                'created_at'    => $token->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $sessions,
        ]);
    }

    /**
     * Revoke a single active session (cannot revoke the current one).
     */
    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        if ($id === $currentId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot revoke the current session. Use logout instead.',
            ], 422);
        }

        $deleted = $request->user()
            ->tokens()
            ->where('id', $id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found.',
            ], 404);
        }

        AuditService::log($request->user(), AuditAction::SessionRevoked, 'personal_access_tokens', $id);

        return response()->json([
            'success' => true,
            'message' => 'Session revoked successfully.',
        ]);
    }

    /**
     * Revoke every session except the current one.
     */
    public function revokeAllSessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        $query = $request->user()->tokens();
        if ($currentId) {
            $query->where('id', '!=', $currentId);
        }

        $count = $query->delete();

        if ($count > 0) {
            AuditService::log($request->user(), AuditAction::SessionRevoked, 'personal_access_tokens', null, [], [
                'revoked_count' => $count,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} other session(s) revoked.",
            'data'    => ['revoked' => $count],
        ]);
    }

    /**
     * Paginated login history for the authenticated user.
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $activities = LoginActivity::where('user_id', $request->user()->id)
            ->orderByDesc('logged_at')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json([
            'success' => true,
            'data'    => $activities->through(fn ($item) => [
                'id'         => $item->id,
                'event'      => $item->event,
                'successful' => $item->successful,
                'ip_address' => $item->ip_address,
                'browser'    => $item->browser,
                'platform'   => $item->platform,
                'device'     => $item->device,
                'logged_at'  => $item->logged_at?->format('Y-m-d H:i:s'),
            ])->toArray(),
        ]);
    }

    /**
     * List all personal access tokens (admin API token registry).
     */
    public function tokens(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $tokens = \Laravel\Sanctum\PersonalAccessToken::query()
            ->with('tokenable')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn ($token) => [
                'id'           => $token->id,
                'name'         => $token->name,
                'abilities'    => $token->abilities ?? [],
                'owner_name'   => $token->tokenable?->name,
                'owner_role'   => $token->tokenable?->role,
                'last_used_at' => $token->last_used_at?->format('Y-m-d H:i:s'),
                'created_at'   => $token->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $tokens->items(),
            'meta'    => [
                'current_page' => $tokens->currentPage(),
                'last_page' => $tokens->lastPage(),
                'per_page' => $tokens->perPage(),
                'total' => $tokens->total(),
            ],
        ]);
    }

    /**
     * Issue a new personal access token (admin).
     */
    public function storeToken(StoreApiTokenRequest $request): JsonResponse
    {
        $user    = $request->user();
        $payload = $request->validated();

        $token = $user->createToken(
            $payload['name'],
            $payload['abilities'] ?? ['*']
        );

        AuditService::log($user, AuditAction::TokenIssued, 'personal_access_tokens', $token->accessToken->id, [], [
            'name'      => $payload['name'],
            'abilities' => $payload['abilities'] ?? ['*'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API token created successfully.',
            'data'    => [
                'id'    => $token->accessToken->id,
                'name'  => $payload['name'],
                'token' => $token->plainTextToken,
            ],
        ], 201);
    }

    /**
     * Revoke a personal access token (admin).
     */
    public function revokeToken(Request $request, int $id): JsonResponse
    {
        $deleted = \Laravel\Sanctum\PersonalAccessToken::query()
            ->where('id', $id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'API token not found.',
            ], 404);
        }

        AuditService::log($request->user(), AuditAction::TokenRevoked, 'personal_access_tokens', $id);

        return response()->json([
            'success' => true,
            'message' => 'API token revoked successfully.',
        ]);
    }
}
