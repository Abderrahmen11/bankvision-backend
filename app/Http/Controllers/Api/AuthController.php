<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginActivity;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AuditService;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactor
    ) {}

    /**
     * Authenticate staff user, verify active status, and issue Sanctum token.
     * When 2FA is enabled the token is withheld until the email code is verified.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        // 1. Verify user existence
        if (!$user) {
            AuditService::log(null, AuditAction::LoginFailed, 'users', null, [], [
                'email'   => $credentials['email'],
                'reason'  => 'unknown_email',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // 2. Verify password check
        if (!Hash::check($credentials['password'], $user->password)) {
            LoginActivity::record($user, 'failed_login', successful: false);
            AuditService::log($user, AuditAction::LoginFailed, 'users', $user->id, [], [
                'reason' => 'wrong_password',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // 3. Check active user status
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is currently ' . $user->status . '. Please contact your system administrator.',
            ], 403);
        }

        // 3. Update last login timestamp
        $user->update([
            'last_login_at' => now(),
        ]);

        LoginActivity::record($user, 'login');

        // 4. Two-factor challenge: withhold the token until the email code is verified
        $settings = $user->settingsOrCreate();
        if ($settings->two_factor_enabled) {
            $this->twoFactor->issueCode($user);

            $payload = [
                'success' => false,
                'requires_2fa' => true,
                'message' => 'A verification code has been sent to your email address.',
                'email' => substr($user->email, 0, 2) . '***' . substr($user->email, strpos($user->email, '@')),
            ];

            // Dev environments ship mail over the log driver — surface that so
            // the code is discoverable without mailbox access.
            if (app()->environment('local', 'development', 'testing') && config('mail.default') === 'log') {
                $payload['dev_hint'] = 'Development: the verification code is written to storage/logs/laravel.log';
            }

            return response()->json($payload, 200);
        }

        // 5. Create Sanctum token & load branch relation
        $token = $user->createToken('bankvision-auth-token')->plainTextToken;
        $user->load('branch');

        $this->welcomeBack($user);
        AuditService::log($user, AuditAction::Login, 'users', $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'token' => $token,
            'user' => UserResource::make($user),
        ], 200);
    }

    /**
     * Complete a 2FA login: verify the emailed code and issue the Sanctum token.
     */
    public function verifyTwoFactorLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification request.',
            ], 422);
        }

        if (!$this->twoFactor->verifyCode($user, $request->input('code'))) {
            LoginActivity::record($user, 'failed_login', successful: false);
            AuditService::log($user, AuditAction::LoginFailed, 'users', $user->id, [], [
                'reason' => 'invalid_2fa_code',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $user->update(['last_login_at' => now()]);
        LoginActivity::record($user, 'login');
        AuditService::log($user, AuditAction::TwoFactorVerified, 'users', $user->id);
        AuditService::log($user, AuditAction::Login, 'users', $user->id);

        $token = $user->createToken('bankvision-auth-token')->plainTextToken;
        $user->load('branch');

        $this->welcomeBack($user);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'token' => $token,
            'user' => UserResource::make($user),
        ], 200);
    }

    /**
     * In-app notification so the bell icon reflects the fresh sign-in.
     */
    private function welcomeBack(User $user): void
    {
        Notification::announce(
            $user->id,
            'New sign-in detected',
            'You signed in to BankVision from a new session.',
            '/dashboard',
            'info'
        );
    }

    /**
     * Re-issue the 2FA code for a pending login challenge.
     * Only works when a challenge is actually pending for the given account.
     */
    public function resendTwoFactorCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification request.',
            ], 422);
        }

        $settings = $user->settingsOrCreate();
        if (!$settings->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification request.',
            ], 422);
        }

        $this->twoFactor->assertResendAllowed($user);
        $this->twoFactor->issueCode($user);

        $payload = [
            'success' => false,
            'requires_2fa' => true,
            'message' => 'A new verification code has been sent to your email address.',
            'email' => substr($user->email, 0, 2) . '***' . substr($user->email, strpos($user->email, '@')),
        ];

        if (app()->environment('local', 'development', 'testing') && config('mail.default') === 'log') {
            $payload['dev_hint'] = 'Development: the verification code is written to storage/logs/laravel.log';
        }

        return response()->json($payload, 200);
    }

    /**
     * Revoke current access token for authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($user = $request->user()) {
            LoginActivity::record($user, 'logout');
            AuditService::log($user, AuditAction::Logout, 'users', $user->id);
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    /**
     * Return authenticated user profile with assigned branch.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => UserResource::make($request->user()->load('branch')),
        ], 200);
    }
}
