<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate staff user, verify active status, and issue Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // 1. Verify user existence & password check
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // 2. Check active user status
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
            'token'   => $token,
            'user'    => $user,
        ], 200);
    }

    /**
     * Revoke current access token for authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

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
            'user'    => $request->user()->load('branch'),
        ], 200);
    }
}
