<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\FirebaseStorageService;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private FirebaseStorageService $storageService,
        private ImageCompressionService $compressionService,
    ) {}

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
        ]);

        // Normalize phone to international format (62xxx) for WhatsApp deep links
        if (!empty($validated['phone_number'])) {
            $phone = preg_replace('/[\s\-\.\(\)]/', '', $validated['phone_number']);
            $phone = ltrim($phone, '+');
            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            }
            $validated['phone_number'] = $phone;
        }

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('driverProfile')),
        ]);
    }

    /**
     * Upload a new avatar for the authenticated user.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar from Firebase Storage
        if ($user->profile_picture) {
            $objectPath = $this->storageService->extractObjectPath($user->profile_picture);
            if ($objectPath) {
                $this->storageService->delete($objectPath);
            }
        }

        // Compress and upload to Firebase
        $compressed = $this->compressionService->compress(
            $request->file('avatar')->getRealPath(), 512, 512, 80
        );
        $firebasePath = 'avatars/avatar_' . $user->id . '_' . time() . '.jpg';
        $url = $this->storageService->upload($firebasePath, $compressed, 'image/jpeg');

        $user->update(['profile_picture' => $url]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('driverProfile')),
        ]);
    }

    /**
     * Soft-delete the authenticated user's account.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // Reject if user has an active ride (as rider or driver)
        $hasActiveRide = $user->rideRequests()
            ->whereIn('status', ['pending', 'matched'])
            ->exists();

        if (!$hasActiveRide && $user->isDriver()) {
            $hasActiveRide = $user->driverRides()
                ->whereIn('status', ['accepted', 'arriving', 'arrived', 'in_progress'])
                ->exists();
        }

        if ($hasActiveRide) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account while you have an active ride.',
            ], 422);
        }

        // If driver is online, go offline
        if ($user->isDriverOnline()) {
            $user->driverProfile->update(['went_online_at' => null]);
        }

        // Delete profile picture from Firebase Storage
        if ($user->profile_picture) {
            $objectPath = $this->storageService->extractObjectPath($user->profile_picture);
            if ($objectPath) {
                $this->storageService->delete($objectPath);
            }
        }

        // Delete KTM photo and wipe driver profile PII
        if ($user->driverProfile) {
            $dp = $user->driverProfile;
            if ($dp->ktm_url) {
                $objectPath = $this->storageService->extractObjectPath($dp->ktm_url);
                if ($objectPath) {
                    $this->storageService->delete($objectPath);
                }
            }
            $dp->update([
                'student_email'    => null,
                'student_id'       => null,
                'student_name'     => null,
                'vehicle_plate'    => null,
                'vehicle_color'    => null,
                'ktm_url'          => null,
                'email_verified_at' => null,
                'is_verified'      => false,
            ]);
        }

        // Revoke all tokens, anonymize identifiers, and clear PII
        $user->tokens()->delete();
        $user->update([
            'name'            => 'Deleted User',
            'email'           => "deleted_{$user->id}@removed",
            'firebase_uid'    => "deleted_{$user->id}",
            'fcm_token'       => null,
            'phone_number'    => null,
            'profile_picture' => null,
        ]);

        // Soft-delete
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}
