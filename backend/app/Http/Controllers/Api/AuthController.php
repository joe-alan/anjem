<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(private FirebaseAuthService $firebaseAuth)
    {
    }

    public function authenticateWithFirebase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'firebase_token' => 'required|string',
            'device_type' => 'required|string|in:rider,driver',
            'device_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $firebaseUser = $this->firebaseAuth->verifyToken($request->firebase_token);
            $user = $this->firebaseAuth->getOrCreateUser($firebaseUser, $request->device_type);

            $token = $user->createToken('mobile-app', ['*'], now()->addDay())->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'firebase_uid' => $user->firebase_uid,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function googleRedirect(): JsonResponse
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return response()->json([
            'redirect_url' => $url
        ]);
    }

    public function googleCallback(Request $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = $this->firebaseAuth->getOrCreateUser([
                'uid' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'email_verified' => true,
            ], $request->query('device_type', 'rider'));

            $token = $user->createToken('mobile-app', ['*'], now()->addDay())->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Google authentication failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();
        $newToken = $user->createToken('mobile-app', ['*'], now()->addDay())->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $newToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->firebase_uid) {
            $this->firebaseAuth->revokeRefreshTokens($user->firebase_uid);
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }
}
