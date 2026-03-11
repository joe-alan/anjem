<?php

namespace App\Http\Controllers\Api;

use App\Events\DriverOnlineStatusChanged;
use App\Events\SessionReplaced;
use App\Http\Controllers\Controller;
use App\Services\FirebaseAuthService;
use App\Services\MatchingQueueService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private FirebaseAuthService $firebaseAuth,
        private NotificationService $notificationService,
        private MatchingQueueService $matchingQueueService,
    ) {}

    public function authenticateWithFirebase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'firebase_token' => 'required|string',
            'device_type' => 'required|string|in:rider,driver',
            'device_id' => 'nullable|string',
            'fcm_token' => 'nullable|string|min:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $firebaseUser = $this->firebaseAuth->verifyToken($request->firebase_token);
            $user = $this->firebaseAuth->getOrCreateUser($firebaseUser, $request->device_type);

            // Update FCM token if provided
            if ($request->fcm_token) {
                $this->notificationService->updateUserToken($user->id, $request->fcm_token);
            }

            // For drivers: enforce single-session at login time.
            // If the driver is currently online on another device, kick them offline
            // and revoke old tokens before issuing the new one.  The old device
            // receives SessionReplaced via WebSocket (broadcast before token deletion
            // so it still has a moment to react) and must go online again explicitly.
            if ($request->device_type === 'driver') {
                $hasOtherTokens = $user->tokens()->exists();

                if ($hasOtherTokens) {
                    $driverProfile = $user->driverProfile;

                    // Always broadcast SessionReplaced so the old device signs out
                    // regardless of whether it was online or just idle on the home screen.
                    broadcast(new SessionReplaced($user->id));

                    if ($driverProfile?->isOnline()) {
                        $this->matchingQueueService->removeFromQueue($user->id);
                        $driverProfile->update(['went_online_at' => null]);
                        broadcast(new DriverOnlineStatusChanged($user, false, null));

                        \Log::info('AuthController: driver kicked offline on new login', [
                            'driver_id' => $user->id,
                        ]);
                    }

                    $user->tokens()->delete();

                    \Log::info('AuthController: old driver sessions revoked on new login', [
                        'driver_id' => $user->id,
                    ]);
                }
            }

            // Create token with role-based abilities (no expiration)
            $abilities = $this->getTokenAbilities($user);
            $token = $user->createToken('mobile-app', $abilities)->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => $user->role, // API backward compatibility: fetch from role
                    'firebase_uid' => $user->firebase_uid,
                    'is_active' => $user->is_active,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function googleRedirect(): JsonResponse
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return response()->json([
            'redirect_url' => $url,
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

            // Create token with role-based abilities (no expiration)
            $abilities = $this->getTokenAbilities($user);
            $token = $user->createToken('mobile-app', $abilities)->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'firebase_uid' => $user->firebase_uid,
                    'user_type' => $user->role, // API backward compatibility: fetch from role
                    'is_active' => $user->is_active,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Google authentication failed',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();

        // Create new token with role-based abilities (no expiration)
        $abilities = $this->getTokenAbilities($user);
        $newToken = $user->createToken('mobile-app', $abilities)->plainTextToken;

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

        $user->update(['fcm_token' => null]);

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Update user's FCM token for push notifications
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|min:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $success = $this->notificationService->updateUserToken($user->id, $request->fcm_token);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'FCM token updated successfully' : 'Failed to update FCM token',
        ], $success ? 200 : 500);
    }

    /**
     * Get token abilities based on user role
     */
    private function getTokenAbilities($user): array
    {
        $baseAbilities = [
            'profile:read',
            'profile:update',
            'locations:read',
        ];

        $roleAbilities = match ($user->role) {
            'rider' => [
                'rider:request-ride',
                'rider:cancel-ride',
                'rider:rate-driver',
            ],
            'driver' => [
                'driver:go-online',
                'driver:accept-ride',
                'driver:complete-ride',
                'driver:update-location',
                'driver:rate-rider',
            ],
            'both' => [
                'rider:request-ride',
                'rider:cancel-ride',
                'rider:rate-driver',
                'driver:go-online',
                'driver:accept-ride',
                'driver:complete-ride',
                'driver:update-location',
                'driver:rate-rider',
            ],
            'admin' => [ // Admin has all abilities
                'rider:request-ride',
                'rider:cancel-ride',
                'rider:rate-driver',
                'driver:go-online',
                'driver:accept-ride',
                'driver:complete-ride',
                'driver:update-location',
                'driver:rate-rider',
            ],
            default => []
        };

        return array_merge($baseAbilities, $roleAbilities);
    }
}
