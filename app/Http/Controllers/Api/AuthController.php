<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * @group Authentication
 *
 * APIs for managing authentication
 */
class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * @response 201 {
     *   "message": "Registration successful.",
     *   "user": { "id": 1, "name": "Bassel", "email": "bassel@test.com" },
     *   "token": "1|abc123",
     *   "token_type": "Bearer"
     * }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => __('messages.register_success'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login
     *
     * @response 200 {
     *   "message": "Login successful.",
     *   "user": { "id": 1, "name": "Bassel", "email": "bassel@test.com" },
     *   "token": "1|abc123",
     *   "token_type": "Bearer"
     * }
     * @response 401 { "message": "Invalid credentials." }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => __('messages.login_success'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Get profile
     *
     * @authenticated
     * @response 200 {
     *   "user": { "id": 1, "name": "Bassel", "email": "bassel@test.com" }
     * }
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'created_at' => $request->user()->created_at,
            ],
        ]);
    }

    /**
     * Logout
     *
     * @authenticated
     * @response 200 { "message": "Logged out successfully." }
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke only the current device's token — other sessions stay active.
        // We query by ID instead of calling delete() on the interface directly,
        // because currentAccessToken() returns HasAbilities which has no delete().
        $request->user()
            ->tokens()
            ->where('id', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'message' => __('messages.logout_success'),
        ]);
    }

    /**
     * Logout from all devices
     *
     * @authenticated
     * @response 200 { "message": "Logged out from all devices successfully." }
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => __('messages.logout_all_success'),
        ]);
    }
}