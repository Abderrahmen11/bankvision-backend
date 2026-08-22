<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate staff user, verify active status, and issue Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        // 1. Verify user existence
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.',
            ], 404);
        }

        // 2. Verify password check
        if (!Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect password.',
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

        // 4. Create Sanctum token & load branch relation
        $token = $user->createToken('bankvision-auth-token')->plainTextToken;
        $user->load('branch');

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'token' => $token,
            'user' => UserResource::make($user),
        ], 200);
    }

    /**
     * Revoke current access token for authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

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
